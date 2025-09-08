<?php
// phpcs:ignoreFile

namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetch saved settings with sane defaults.
 */
function get_settings(): array {
	$defaults = [
		'autosort_enabled'  => true,
		'admin_autosort'    => true,
		'taxonomies'        => [],
		'secondary_orderby' => 'name', // or 'term_id'
		'backend'           => 'meta',
		'capability'        => defined('YTO_CAP') ? YTO_CAP : 'manage_categories',
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
