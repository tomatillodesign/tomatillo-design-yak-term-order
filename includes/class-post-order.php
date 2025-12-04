<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Post Order - Drag-and-drop ordering for posts and custom post types.
 * Uses the built-in menu_order field in wp_posts table.
 */
class Post_Order {

	private array $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;

		// Admin UI hooks
		if ( is_admin() ) {
			add_action( 'load-edit.php', [ $this, 'maybe_boot_admin' ] );
			add_action( 'wp_ajax_yto_save_post_order', [ $this, 'ajax_save_order' ] );
		}

		// Front-end autosort
		add_action( 'pre_get_posts', [ $this, 'maybe_autosort_query' ], 99 );

		// FacetWP integration
		add_filter( 'facetwp_query_args', [ $this, 'facetwp_query_args' ], 10, 2 );
	}

	/**
	 * Boot admin UI on edit.php for enabled post types.
	 * Always in ordering mode for enabled post types.
	 */
	public function maybe_boot_admin(): void {
		global $typenow;

		if ( empty( $typenow ) ) {
			return;
		}

		$enabled = $this->get_enabled_post_types();
		if ( ! in_array( $typenow, $enabled, true ) ) {
			return;
		}

		// Always in ordering mode for enabled post types
		add_filter( 'admin_body_class', [ $this, 'add_ordering_body_class' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'manage_posts_columns', [ $this, 'add_order_column' ], 5 );
		add_action( 'manage_posts_custom_column', [ $this, 'render_order_column' ], 10, 2 );
		add_action( 'all_admin_notices', [ $this, 'render_order_panel' ] );
	}

	/**
	 * Add ordering mode class to body.
	 */
	public function add_ordering_body_class( string $classes ): string {
		return $classes . ' yto-ordering-mode';
	}

	/**
	 * Enqueue JS/CSS for the post list ordering UI.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'yto-post-order-js',
			YTO_PLUGIN_URL . 'assets/admin-posts.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			YTO_VERSION,
			true
		);
		wp_enqueue_style(
			'yto-post-order-css',
			YTO_PLUGIN_URL . 'assets/admin-posts.css',
			[],
			YTO_VERSION
		);

		wp_localize_script( 'yto-post-order-js', 'ytoPostOrder', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'yto_save_post_order' ),
			'i18n'    => [
				'saving'      => __( 'Saving...', 'yak-term-order' ),
				'saved'       => __( 'Order saved!', 'yak-term-order' ),
				'error'       => __( 'Error saving order. Please try again.', 'yak-term-order' ),
				'enable'      => __( 'Enable Ordering', 'yak-term-order' ),
				'disable'     => __( 'Exit Ordering Mode', 'yak-term-order' ),
				'moveUp'      => __( 'Move up', 'yak-term-order' ),
				'moveDown'    => __( 'Move down', 'yak-term-order' ),
				'movedTo'     => __( 'moved to position', 'yak-term-order' ),
				'of'          => __( 'of', 'yak-term-order' ),
			],
		] );
	}

	/**
	 * Add drag handle column to posts table.
	 */
	public function add_order_column( array $columns ): array {
		$new_columns = [];
		$new_columns['yto_drag'] = '<span class="yto-drag-header" title="' . esc_attr__( 'Drag to reorder', 'yak-term-order' ) . '">⋮⋮</span>';
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
		}
		return $new_columns;
	}

	/**
	 * Render drag handle in column.
	 */
	public function render_order_column( string $column, int $post_id ): void {
		if ( 'yto_drag' !== $column ) {
			return;
		}
		$order = (int) get_post_field( 'menu_order', $post_id );
		printf(
			'<span class="yto-drag-handle" data-id="%d" title="%s">⋮⋮</span><span class="yto-order-value">%d</span>',
			$post_id,
			esc_attr__( 'Drag to reorder', 'yak-term-order' ),
			$order
		);
	}

	/**
	 * Render the ordering panel above the posts table.
	 */
	public function render_order_panel(): void {
		global $typenow;

		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		$post_type_obj = get_post_type_object( $typenow );
		$label = $post_type_obj ? $post_type_obj->labels->name : $typenow;

		?>
		<div id="yto-post-order-panel" class="notice" data-post-type="<?php echo esc_attr( $typenow ); ?>">
			<div class="yto-panel-header">
				<span class="yto-drag-icon" aria-hidden="true">⋮⋮</span>
				<strong><?php echo esc_html( sprintf( __( 'Drag to reorder %s', 'yak-term-order' ), strtolower( $label ) ) ); ?></strong>
				<span id="yto-save-status" class="yto-autosave-status"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler to save post order.
	 */
	public function ajax_save_order(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		check_ajax_referer( 'yto_save_post_order', 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];

		if ( empty( $post_type ) || ! in_array( $post_type, $this->get_enabled_post_types(), true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid post type' ], 400 );
		}

		if ( empty( $order ) ) {
			wp_send_json_error( [ 'message' => 'No posts to order' ], 400 );
		}

		global $wpdb;

		// Normalize to 10, 20, 30...
		$position = 10;
		$updated = 0;

		foreach ( $order as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== $post_type ) {
				continue;
			}

			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => $position ],
				[ 'ID' => $post_id ],
				[ '%d' ],
				[ '%d' ]
			);

			clean_post_cache( $post_id );
			$position += 10;
			$updated++;
		}

		/**
		 * Fires after Yak Term Order updates post order.
		 *
		 * @param string $post_type
		 * @param array  $order
		 * @param int    $user_id
		 */
		do_action( 'yak_term_order/post_order_updated', $post_type, $order, get_current_user_id() );

		wp_send_json_success( [ 'saved' => $updated ] );
	}

	/**
	 * Auto-sort queries for enabled post types (admin + front-end).
	 */
	public function maybe_autosort_query( \WP_Query $query ): void {
		// Get the queried post type
		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) ) {
			return;
		}

		// Normalize to array
		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
		$enabled = $this->get_enabled_post_types();

		// Check if any queried post type is enabled
		$matching = array_intersect( $post_types, $enabled );
		if ( empty( $matching ) ) {
			return;
		}

		// Allow opt-out via query arg
		if ( $query->get( 'ignore_menu_order' ) ) {
			return;
		}

		// Admin: Always sort by menu_order for enabled post types on edit.php
		if ( is_admin() ) {
			global $pagenow;
			if ( 'edit.php' === $pagenow && $query->is_main_query() ) {
				// Only override if user hasn't explicitly chosen a different sort
				$orderby_param = $_GET['orderby'] ?? '';
				if ( empty( $orderby_param ) || 'menu_order' === $orderby_param ) {
					$query->set( 'orderby', 'menu_order' );
					$query->set( 'order', 'ASC' );
				}
			}
			return;
		}

		// Front-end: Check if autosort is enabled in settings
		$opts = get_settings();
		if ( empty( $opts['post_autosort_frontend'] ) ) {
			return;
		}

		// Don't override explicit orderby settings (unless it's 'date' which is default)
		$current_orderby = $query->get( 'orderby' );
		if ( ! empty( $current_orderby ) && 'date' !== $current_orderby && 'menu_order' !== $current_orderby ) {
			return;
		}

		// Apply menu_order sorting
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * FacetWP integration - modify query args when configured.
	 */
	public function facetwp_query_args( array $query_args, $class ): array {
		$opts = get_settings();

		// Check if any enabled post type is being queried
		$post_type = $query_args['post_type'] ?? '';
		if ( empty( $post_type ) ) {
			return $query_args;
		}

		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
		$enabled = $this->get_enabled_post_types();

		$matching = array_intersect( $post_types, $enabled );
		if ( empty( $matching ) ) {
			return $query_args;
		}

		// Only apply if front-end autosort is enabled or explicit orderby is menu_order
		$explicit_menu_order = isset( $query_args['orderby'] ) && 'menu_order' === $query_args['orderby'];
		if ( ! $explicit_menu_order && empty( $opts['post_autosort_frontend'] ) ) {
			return $query_args;
		}

		// Apply menu_order sorting
		$query_args['orderby'] = 'menu_order';
		$query_args['order'] = 'ASC';

		return $query_args;
	}

	/**
	 * Get enabled post types from settings.
	 */
	private function get_enabled_post_types(): array {
		$opts = get_settings();
		$types = $opts['post_types'] ?? [];
		$types = apply_filters( 'yak_term_order/allowed_post_types', $types );
		return array_filter( array_map( 'sanitize_key', (array) $types ) );
	}

	/**
	 * Get required capability.
	 */
	private function capability(): string {
		$opts = get_settings();
		return $opts['capability'] ?: 'edit_others_posts';
	}
}

