<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inline ordering panel on edit-tags.php (taxonomy editor).
 * - Drag children, click Save (AJAX).
 * - Save always normalizes to 10, 20, 30… in the visual order.
 * - Admin autosort (post-query) makes tables & checklists honor it.
 *
 * This version FILTERS the Parent dropdown to ONLY show parents that have
 * something to reorder:
 *   - Top level is included only if it has ≥ 2 root terms.
 *   - Individual parents are included only if they have ≥ 2 direct children.
 */
class Inline_Order {

	public function __construct( array $cfg ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		add_action( 'load-edit-tags.php', [ $this, 'maybe_boot' ] );

		// AJAX handler (admin-only)
		add_action( 'wp_ajax_yto_inline_save_order', [ $this, 'ajax_save' ] );
	}

	public function maybe_boot(): void {
		if ( 'edit-tags.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) return;

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
		if ( '' === $taxonomy || ! in_array( $taxonomy, allowed_taxonomies(), true ) ) return;

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'all_admin_notices', [ $this, 'render_panel' ] );
	}

	public function enqueue( string $hook ): void {
		if ( 'edit-tags.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) return;

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'yto-order-js', YTO_PLUGIN_URL . 'assets/admin.js', [ 'jquery', 'jquery-ui-sortable' ], YTO_VERSION, true );
		wp_enqueue_style( 'yto-order-css', YTO_PLUGIN_URL . 'assets/admin.css', [], YTO_VERSION );

		$extra = '
		#yto-inline-panel{margin:12px 0 18px;padding:12px 16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
		#yto-inline-panel .desc{margin:6px 0 12px;color:#555d66}
		#yto-inline-panel .row{display:flex;gap:12px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
		#yto-inline-panel .row select{min-width:220px}
		#yto-inline-panel .actions{display:flex;gap:8px;align-items:center;margin-top:8px}
		#yto-inline-panel .toggle-link{margin-left:auto}
		';
		wp_add_inline_style( 'yto-order-css', $extra );
	}

	public function render_panel(): void {
		if ( ! current_user_can( $this->cap() ) ) return;

		$taxonomy   = sanitize_key( $_GET['taxonomy'] ?? '' );
		if ( '' === $taxonomy || ! in_array( $taxonomy, allowed_taxonomies(), true ) ) return;

		$tax_obj    = get_taxonomy( $taxonomy );
		$tax_label  = $tax_obj ? ( $tax_obj->labels->singular_name ?: $tax_obj->label ) : $taxonomy;

		$expanded   = isset( $_GET['yto_order'] ) && '1' === (string) $_GET['yto_order'];
		$parent_id  = isset( $_GET['yto_parent'] ) ? absint( $_GET['yto_parent'] ) : 0;
		$post_type  = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

		$parent_choices = $this->parent_choices( $taxonomy );

		// If no eligible parents at all, bail with a friendly note.
		if ( empty( $parent_choices ) ) {
			echo '<div id="yto-inline-panel" class="notice">';
			echo '<h2 style="margin-top:0;">' . esc_html( sprintf( __( 'Order Terms — %s', 'yak-term-order' ), $tax_label ) ) . '</h2>';
			echo '<p class="desc">' . esc_html__( 'No branches have 2 or more children, so there is nothing to reorder yet.', 'yak-term-order' ) . '</p>';
			echo '</div>';
			return;
		}

		// If currently selected parent isn’t eligible anymore, default to the first option.
		if ( ! array_key_exists( $parent_id, $parent_choices ) ) {
			$parent_id = (int) array_key_first( $parent_choices );
		}

		$siblings = $expanded ? $this->get_siblings( $taxonomy, $parent_id ) : [];
		$nonce    = wp_create_nonce( 'yto_inline_save' );

		echo '<div id="yto-inline-panel" class="notice">';
		echo '<h2 style="margin-top:0;">' . esc_html( sprintf( __( 'Order Terms — %s', 'yak-term-order' ), $tax_label ) ) . '</h2>';
		echo '<p class="desc">' . esc_html__( 'Pick a parent and drag its children. Click Save — the order is normalized to 10, 20, 30… automatically.', 'yak-term-order' ) . '</p>';

		// Selector row (GET refresh)
		echo '<form method="get" action="" class="row">';
		echo '<input type="hidden" name="taxonomy" value="' . esc_attr( $taxonomy ) . '" />';
		if ( '' !== $post_type ) {
			echo '<input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '" />';
		}
		echo '<label for="yto_parent_sel"><strong>' . esc_html__( 'Parent', 'yak-term-order' ) . '</strong></label> ';
		echo '<select id="yto_parent_sel" name="yto_parent">';
		foreach ( $parent_choices as $pid => $label ) {
			printf( '<option value="%d"%s>%s</option>', $pid, selected( $pid, $parent_id, false ), esc_html( $label ) );
		}
		echo '</select> ';
		echo '<input type="hidden" name="yto_order" value="1" />';
		submit_button( __( 'Load', 'yak-term-order' ), 'secondary', '', false );

		// Toggle link
		$toggle_url = remove_query_arg( [ 'yto_order', 'yto_parent' ] );
		$toggle_txt = $expanded ? __( 'Hide ordering panel', 'yak-term-order' ) : __( 'Show ordering panel', 'yak-term-order' );
		$toggle_url = $expanded ? $toggle_url : add_query_arg( 'yto_order', 1, $toggle_url );
		echo '<a class="toggle-link" href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_txt ) . '</a>';
		echo '</form>';

		if ( $expanded ) {
			// If the chosen parent ended up with < 2 children, show a friendly hint instead of the sorter.
			if ( count( $siblings ) < 2 ) {
				echo '<p>' . esc_html__( 'This parent has fewer than two children, so there is nothing to reorder. Choose a different parent.', 'yak-term-order' ) . '</p>';
			} else {
				// Root container carries data for AJAX save
				printf(
					'<div id="yto-order-root" data-taxonomy="%s" data-parent="%d" data-nonce="%s" data-ajax="%s">',
					esc_attr( $taxonomy ),
					(int) $parent_id,
					esc_attr( $nonce ),
					esc_url( admin_url( 'admin-ajax.php' ) )
				);

				echo '<ul id="yto-sortable" class="yto-list" role="listbox" aria-label="' . esc_attr__( 'Reorder terms', 'yak-term-order' ) . '">';
				foreach ( $siblings as $term ) {
					$meta = yto_term_order( $term->term_id );
					printf(
						'<li class="yto-item" role="option" tabindex="0" data-id="%d" aria-grabbed="false">
							<span class="yto-handle" aria-hidden="true">⋮⋮</span>
							<strong class="yto-name">%s</strong>
							<span class="yto-meta">%s</span>
							<span class="yto-actions">
								<a href="#" class="button button-small yto-up" aria-label="%s">↑</a>
								<a href="#" class="button button-small yto-down" aria-label="%s">↓</a>
							</span>
						</li>',
						$term->term_id,
						esc_html( $term->name ),
						$meta['has'] ? sprintf( esc_html__( 'Order: %d', 'yak-term-order' ), $meta['val'] ) : esc_html__( 'Unordered', 'yak-term-order' ),
						esc_attr__( 'Move up', 'yak-term-order' ),
						esc_attr__( 'Move down', 'yak-term-order' )
					);
				}
				echo '</ul>';

				echo '<div id="yto-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>';

				echo '<div class="actions">';
				echo '<button type="button" id="yto-save" class="button button-primary">' . esc_html__( 'Save Order', 'yak-term-order' ) . '</button>';
				echo '<p class="description" style="margin:4px 0 0;">' .
					esc_html__( 'Saving writes 10, 20, 30… to this branch in the current order (you can insert numbers between later).', 'yak-term-order' ) .
					'</p>';
				echo '</div>';

				echo '</div>'; // #yto-order-root
			}
		}

		echo '</div>';
	}

	/**
	 * AJAX save: normalize to 10/20/30… in the posted order.
	 */
	public function ajax_save(): void {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		check_ajax_referer( 'yto_inline_save', 'nonce' );

		$taxonomy  = sanitize_key( $_POST['taxonomy'] ?? '' );
		$parent_id = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$ordered   = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];

		if ( '' === $taxonomy || ! in_array( $taxonomy, allowed_taxonomies(), true ) ) {
			wp_send_json_error( [ 'message' => 'bad taxonomy' ], 400 );
		}

		// Fallback to current siblings if nothing posted
		if ( empty( $ordered ) ) {
			$ordered = wp_list_pluck( $this->get_siblings( $taxonomy, $parent_id, true ), 'term_id' );
		}

		yto_set_sibling_order( $taxonomy, $parent_id, $ordered, 10, 10 );

		wp_send_json_success( [ 'saved' => count( $ordered ) ] );
	}

	private function cap(): string {
		$opts = get_settings();
		return $opts['capability'] ?: 'manage_categories';
	}

	/**
	 * Build Parent choices:
	 *  - Include Top level (0) ONLY if there are ≥ 2 root terms
	 *  - Include terms ONLY if they have ≥ 2 direct children
	 */
	private function parent_choices( string $taxonomy ): array {
		$out = [];

		// Top-level roots (children of parent 0).
		$root_ids = get_terms( [
			'taxonomy'   => $taxonomy,
			'parent'     => 0,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $root_ids ) && count( $root_ids ) >= 2 ) {
			$out[0] = __( 'Top level', 'yak-term-order' );
		}

		// Consider every term as a potential parent; include only if it has ≥ 2 direct children.
		$all_terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'all',
		] );
		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			return $out;
		}

		// Optional: sort for nicer dropdown.
		$all_terms = yto_sort_terms( $all_terms );

		foreach ( $all_terms as $t ) {
			// Fetch at most 2 children fast; if < 2, nothing to reorder.
			$child_ids = get_terms( [
				'taxonomy'   => $taxonomy,
				'parent'     => (int) $t->term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 2,
			] );
			if ( ! is_wp_error( $child_ids ) && count( (array) $child_ids ) >= 2 ) {
				$out[ $t->term_id ] = $t->name;
			}
		}

		return $out;
	}

	private function get_siblings( string $taxonomy, int $parent_id, bool $apply_sort = true ): array {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'parent'     => $parent_id,
			'hide_empty' => false,
			'fields'     => 'all',
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) return [];
		return $apply_sort ? yto_sort_terms( $terms ) : $terms;
	}
}
