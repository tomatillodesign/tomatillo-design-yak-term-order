<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Autosort {

	private array $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;

		/**
		 * SAFEST PATH:
		 * Sort AFTER the query using the `get_terms` filter (array of WP_Term objects).
		 * - No SQL surgery
		 * - Shows ALL terms (numbered + unnumbered)
		 * - Plays nice with Quick Edit / wp_terms_checklist and term editor list
		 */
		add_filter( 'get_terms', [ $this, 'sort_terms_array' ], 99, 3 );

		/**
		 * FacetWP-specific: Sort the rendered choices.
		 */
		add_filter( 'facetwp_facet_render_args', [ $this, 'facetwp_render_args' ], 10, 2 );
	}

	/**
	 * Sort term result arrays in PHP using Yak Term Order rules:
	 *   - Terms with explicit order meta first (ASC)
	 *   - Then the rest A→Z
	 *   - Stable tie-breaker by name
	 *
	 * @param array           $terms       Array of WP_Term (or other) results.
	 * @param array|string    $taxonomies  Taxonomies used in the query.
	 * @param array           $args        Query vars.
	 * @return array
	 */
	public function sort_terms_array( $terms, $taxonomies, $args ) {
		$debug = defined( 'YTO_DEBUG' ) && YTO_DEBUG;

		// 0) Must be an array of term objects
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return $terms;
		}
		if ( ! $terms || ! is_object( $terms[0] ) || ! ( $terms[0] instanceof \WP_Term ) ) {
			// Don't touch arrays of IDs, name lists, or special shapes.
			return $terms;
		}

		// 1) Exactly one taxonomy, and it must be enabled in settings
		$tax_list = array_values( array_filter( array_map( 'sanitize_key', (array) $taxonomies ) ) );
		if ( 1 !== count( $tax_list ) ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - multiple taxonomies: ' . print_r( $tax_list, true ) );
			}
			return $terms;
		}
		$taxonomy = $tax_list[0];
		if ( ! in_array( $taxonomy, allowed_taxonomies(), true ) ) {
			if ( $debug ) {
				error_log( "YTO: Skipping - taxonomy '$taxonomy' not enabled. Allowed: " . print_r( allowed_taxonomies(), true ) );
			}
			return $terms;
		}

		// Log that we're processing this taxonomy
		if ( $debug ) {
			$term_names = array_map( fn( $t ) => $t->name, $terms );
			error_log( "YTO: Processing taxonomy '$taxonomy' with terms: " . implode( ', ', $term_names ) );
			error_log( 'YTO: Args: ' . print_r( $args, true ) );
		}

		// 2) Respect settings (front/admin) and the explicit ignore flag
		if ( ! autosort_context_enabled( $taxonomy, (array) $args ) ) {
			if ( $debug ) {
				error_log( "YTO: Skipping - autosort not enabled for context (is_admin=" . ( is_admin() ? 'yes' : 'no' ) . ')' );
			}
			return $terms;
		}
		if ( ! empty( $args[ YTO_IGNORE_ARG ] ) ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - ignore flag set' );
			}
			return $terms;
		}

		// 3) Only sort when we have full objects; `fields` variants we already bailed above.
		$fields = isset( $args['fields'] ) ? strtolower( (string) $args['fields'] ) : 'all';
		if ( 'all' !== $fields ) {
			if ( $debug ) {
				error_log( "YTO: Skipping - fields='$fields' (not 'all')" );
			}
			return $terms;
		}

		// 4) Optional: if caller explicitly asked for an incompatible order, leave it alone
		$requested = isset( $args['orderby'] ) ? strtolower( (string) $args['orderby'] ) : '';
		if ( in_array( $requested, [ 'include', 'slug', 'id', 'term_id', 'term_group', 'count', 'none', 'meta_value', 'meta_value_num' ], true ) ) {
			if ( $debug ) {
				error_log( "YTO: Skipping - incompatible orderby='$requested'" );
			}
			return $terms;
		}

		// 5) Sort using our helper (numbered first, then A→Z). This never hides terms.
		if ( function_exists( __NAMESPACE__ . '\\yto_sort_terms' ) ) {
			$before = array_map( fn( $t ) => $t->name, $terms );
			$terms = yto_sort_terms( $terms );
			$after = array_map( fn( $t ) => $t->name, $terms );
			
			if ( $debug ) {
				error_log( 'YTO: ✓ SORTED - Before: ' . implode( ', ', $before ) );
				error_log( 'YTO: ✓ SORTED - After:  ' . implode( ', ', $after ) );
			}
		}

		return $terms;
	}

	/**
	 * FacetWP render args filter - sort choices after FacetWP builds them.
	 * This is safer than modifying SQL.
	 * 
	 * @param array $args The render arguments (includes 'values' array and 'facet' array).
	 * @return array
	 */
	public function facetwp_render_args( $args ) {
		$debug = defined( 'YTO_DEBUG' ) && YTO_DEBUG;
		
		// FacetWP includes the facet config inside $args['facet']
		if ( ! isset( $args['facet'] ) ) {
			if ( $debug ) {
				error_log( 'YTO: FacetWP - no facet config in args' );
			}
			return $args;
		}

		$facet = $args['facet'];
		
		if ( $debug ) {
			error_log( 'YTO: FacetWP render_args filter called for facet: ' . ( $facet['name'] ?? 'unknown' ) );
			error_log( 'YTO: Facet type: ' . ( $facet['type'] ?? 'unknown' ) );
			error_log( 'YTO: Facet source: ' . ( $facet['source'] ?? 'unknown' ) );
			error_log( 'YTO: Facet orderby: ' . ( $facet['orderby'] ?? 'default' ) );
		}

		// Respect FacetWP's own sort setting - only apply custom order if FacetWP is set to "term_order"
		if ( isset( $facet['orderby'] ) && 'term_order' !== $facet['orderby'] ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - FacetWP has specific orderby: ' . $facet['orderby'] );
			}
			return $args;
		}

		// Only process taxonomy facets
		if ( ! isset( $facet['source'] ) || empty( $args['values'] ) ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - no source or no values' );
			}
			return $args;
		}

		// Check if this facet's source is an enabled taxonomy
		// FacetWP source format: 'tax/taxonomy_name'
		$source_parts = explode( '/', $facet['source'] );
		if ( 'tax' !== $source_parts[0] || ! isset( $source_parts[1] ) ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - not a taxonomy source' );
			}
			return $args;
		}

		$taxonomy = $source_parts[1];
		
		if ( ! in_array( $taxonomy, allowed_taxonomies(), true ) ) {
			if ( $debug ) {
				error_log( "YTO: Skipping - taxonomy '$taxonomy' not enabled" );
			}
			return $args;
		}

		$opts = get_settings();
		if ( empty( $opts['autosort_enabled'] ) ) {
			if ( $debug ) {
				error_log( 'YTO: Skipping - autosort not enabled in settings' );
			}
			return $args;
		}

		if ( $debug ) {
			$before = array_map( function( $v ) { return $v['label'] ?? '?'; }, $args['values'] );
			error_log( 'YTO: FacetWP choices BEFORE sort: ' . implode( ', ', $before ) );
		}

		// Sort the values array by our term order
		usort( $args['values'], function( $a, $b ) use ( $taxonomy, $debug ) {
			// Get term IDs from the facet value (term slug)
			$term_a = get_term_by( 'slug', $a['facet_value'], $taxonomy );
			$term_b = get_term_by( 'slug', $b['facet_value'], $taxonomy );

			if ( ! $term_a || ! $term_b ) {
				return 0; // Keep original order if we can't find terms
			}

			$order_a = yto_term_order( $term_a->term_id );
			$order_b = yto_term_order( $term_b->term_id );

			// Terms with order first
			if ( $order_a['has'] && ! $order_b['has'] ) {
				return -1;
			}
			if ( $order_b['has'] && ! $order_a['has'] ) {
				return 1;
			}

			// Both have order: sort by value
			if ( $order_a['has'] && $order_b['has'] ) {
				if ( $order_a['val'] !== $order_b['val'] ) {
					return $order_a['val'] <=> $order_b['val'];
				}
			}

			// Tie or both unordered: alphabetical
			return strcasecmp( $a['label'] ?? '', $b['label'] ?? '' );
		} );

		if ( $debug ) {
			$after = array_map( function( $v ) { return $v['label'] ?? '?'; }, $args['values'] );
			error_log( 'YTO: ✓ FacetWP choices AFTER sort: ' . implode( ', ', $after ) );
		}

		return $args;
	}
}
