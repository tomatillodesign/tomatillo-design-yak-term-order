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
		// 0) Must be an array of term objects
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return $terms;
		}
		if ( ! $terms || ! is_object( $terms[0] ) || ! ( $terms[0] instanceof \WP_Term ) ) {
			// Don't touch arrays of IDs, name lists, or special shapes.
			return $terms;
		}

		// 1) Skip FacetWP AJAX (front-end handled in your template)
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && 'facetwp_refresh' === $_POST['action'] ) {
			return $terms;
		}

		// 2) Exactly one taxonomy, and it must be enabled in settings
		$tax_list = array_values( array_filter( array_map( 'sanitize_key', (array) $taxonomies ) ) );
		if ( 1 !== count( $tax_list ) ) {
			return $terms;
		}
		$taxonomy = $tax_list[0];
		if ( ! in_array( $taxonomy, allowed_taxonomies(), true ) ) {
			return $terms;
		}

		// 3) Respect settings (front/admin) and the explicit ignore flag
		if ( ! autosort_context_enabled( $taxonomy, (array) $args ) ) {
			return $terms;
		}
		if ( ! empty( $args[ YTO_IGNORE_ARG ] ) ) {
			return $terms;
		}

		// 4) Only sort when we have full objects; `fields` variants we already bailed above.
		$fields = isset( $args['fields'] ) ? strtolower( (string) $args['fields'] ) : 'all';
		if ( 'all' !== $fields ) {
			return $terms;
		}

		// 5) Optional: if caller explicitly asked for an incompatible order, leave it alone
		$requested = isset( $args['orderby'] ) ? strtolower( (string) $args['orderby'] ) : '';
		if ( in_array( $requested, [ 'include', 'slug', 'id', 'term_id', 'term_group', 'count', 'none', 'meta_value', 'meta_value_num' ], true ) ) {
			return $terms;
		}

		// 6) Sort using our helper (numbered first, then A→Z). This never hides terms.
		if ( function_exists( __NAMESPACE__ . '\\yto_sort_terms' ) ) {
			$terms = yto_sort_terms( $terms );
		}

		return $terms;
	}
}
