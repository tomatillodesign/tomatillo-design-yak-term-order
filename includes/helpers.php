<?php
// phpcs:ignoreFile

namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetch saved settings with sane defaults.
 */
function get_settings(): array {
	$defaults = [
		'autosort_enabled'       => true,
		'admin_autosort'         => true,
		'taxonomies'             => [],
		'secondary_orderby'      => 'name', // or 'term_id'
		'backend'                => 'meta',
		'capability'             => defined('YTO_CAP') ? YTO_CAP : 'manage_categories',
		'post_types'             => [],
		'post_autosort_frontend' => false,
	];
	$saved = get_option( YTO_OPTION_KEY );
	return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
}

/**
 * Allowed taxonomies = saved + developer filter.
 */
function allowed_taxonomies(): array {
	$opts   = get_settings();
	$allow  = is_array( $opts['taxonomies'] ) ? $opts['taxonomies'] : [];
	$allow  = apply_filters( 'yak_term_order/allowed_taxonomies', $allow );
	$allow  = array_filter( array_map( 'sanitize_key', (array) $allow ) );
	return array_values( array_unique( $allow ) );
}

/**
 * Secondary ORDER BY SQL fragment.
 */
function secondary_orderby_sql( string $taxonomy ): string {
	$opts = get_settings();
	$by   = $opts['secondary_orderby'] === 'term_id' ? 't.term_id' : 't.name';
	$sql  = $by . ' ASC';
	return apply_filters( 'yak_term_order/secondary_orderby', $sql, $taxonomy );
}

/**
 * Context gates (front end vs admin).
 */
function autosort_context_enabled( string $taxonomy, array $args ): bool {
	$opts = get_settings();

	// Opt-out escape hatch.
	if ( ! empty( $args[ YTO_IGNORE_ARG ] ) ) {
		return false;
	}

	$allowed = in_array( $taxonomy, allowed_taxonomies(), true );
	if ( ! $allowed ) {
		return false;
	}

	// Front end
	if ( ! is_admin() ) {
		return (bool) apply_filters( 'yak_term_order/autosort_enabled', (bool) $opts['autosort_enabled'], $taxonomy, 'frontend' );
	}

	// Admin screens
	return (bool) apply_filters( 'yak_term_order/autosort_enabled', (bool) $opts['admin_autosort'], $taxonomy, 'admin' );
}

/**
 * Return a term's manual order and whether it was explicitly set.
 *
 * @param int $term_id
 * @return array{has:bool, val:int}
 */
function yto_term_order( int $term_id ): array {
	// meta exists => user explicitly set (even if value is 0).
	$has = metadata_exists( 'term', $term_id, YTO_META_KEY );
	$val = (int) get_term_meta( $term_id, YTO_META_KEY, true );
	return [ 'has' => (bool) $has, 'val' => $val ];
}

/**
 * Sort an array of WP_Term objects so that:
 *  1) Terms WITH a manual order come first (lower numbers first)
 *  2) Terms WITHOUT a manual order follow (A→Z by name)
 *  3) Ties (same number) break by A→Z
 *
 * Safe to call on any array (no-op for empty / WP_Error).
 *
 * @param array<int,\WP_Term> $terms
 * @return array<int,\WP_Term>
 */
function yto_sort_terms( array $terms ): array {
	if ( empty( $terms ) ) {
		return $terms;
	}

	usort(
		$terms,
		function ( \WP_Term $a, \WP_Term $b ) {
			$a_info = yto_term_order( $a->term_id );
			$b_info = yto_term_order( $b->term_id );

			// Ordered terms before unordered.
			if ( $a_info['has'] && ! $b_info['has'] ) {
				return -1;
			}
			if ( $b_info['has'] && ! $a_info['has'] ) {
				return 1;
			}

			// Both ordered: numeric ASC.
			if ( $a_info['has'] && $b_info['has'] ) {
				if ( $a_info['val'] !== $b_info['val'] ) {
					return $a_info['val'] <=> $b_info['val'];
				}
			}

			// Tie or both unordered: Name A→Z.
			return strcasecmp( $a->name, $b->name );
		}
	);

	return $terms;
}

/**
 * Persist sibling order as sequential integers (10, 20, 30 …).
 *
 * @param string $taxonomy     Taxonomy slug.
 * @param int    $parent_id    Parent term ID (0 for top-level).
 * @param int[]  $ordered_ids  Term IDs in the desired visual order.
 * @param int    $start        Starting value (default 10).
 * @param int    $step         Increment (default 10).
 * @return void
 */
function yto_set_sibling_order( string $taxonomy, int $parent_id, array $ordered_ids, int $start = 10, int $step = 10 ): void {
	$ordered_ids = array_values( array_unique( array_map( 'absint', $ordered_ids ) ) );
	if ( empty( $ordered_ids ) ) {
		return;
	}

	$pos = $start;

	foreach ( $ordered_ids as $tid ) {
		$term = get_term( $tid, $taxonomy );
		if ( $term && ! is_wp_error( $term ) && (int) $term->parent === (int) $parent_id ) {
			update_term_meta( $tid, YTO_META_KEY, (int) $pos );
			$pos += $step;
		}
	}

	// Invalidate caches so admin lists reflect changes immediately.
	clean_term_cache( $ordered_ids, $taxonomy, true );

	/**
	 * Fires after Yak Term Order updates a branch.
	 *
	 * @param string $taxonomy
	 * @param int    $parent_id
	 * @param array  $ordered_ids
	 * @param int    $user_id
	 */
	do_action( 'yak_term_order/updated', $taxonomy, $parent_id, $ordered_ids, get_current_user_id() );
}
