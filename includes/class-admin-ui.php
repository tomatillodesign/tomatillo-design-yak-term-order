<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin_UI {

	private array $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;

		add_action( 'admin_init', [ $this, 'register_hooks' ] );
	}

	public function register_hooks(): void {
		foreach ( allowed_taxonomies() as $tax ) {
			// Add form (create new term)
			add_action( "{$tax}_add_form_fields", [ $this, 'render_add_field' ] );
			// Edit form (existing term)
			add_action( "{$tax}_edit_form_fields", [ $this, 'render_edit_field' ], 10, 2 );
			// Save
			add_action( "created_{$tax}", [ $this, 'save_term_order' ], 10, 2 );
			add_action( "edited_{$tax}",  [ $this, 'save_term_order' ], 10, 2 );
		}
	}

	public function render_add_field( string $taxonomy ): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}
		?>
		<div class="form-field term-yto-order-wrap">
			<label for="yak_term_order"><?php esc_html_e( 'Order', 'yak-term-order' ); ?></label>
			<input type="number" name="yak_term_order" id="yak_term_order" min="0" step="1" value="0" />
			<p class="description"><?php esc_html_e( 'Lower numbers appear first among siblings. Leave at 0 and adjust later.', 'yak-term-order' ); ?></p>
		</div>
		<?php
	}

	public function render_edit_field( \WP_Term $term, string $taxonomy ): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}
		$value = (int) get_term_meta( $term->term_id, YTO_META_KEY, true );
		?>
		<tr class="form-field term-yto-order-wrap">
			<th scope="row"><label for="yak_term_order"><?php esc_html_e( 'Order', 'yak-term-order' ); ?></label></th>
			<td>
				<input type="number" name="yak_term_order" id="yak_term_order" min="0" step="1" value="<?php echo esc_attr( $value ); ?>" />
				<p class="description"><?php esc_html_e( 'Lower numbers appear first among siblings. This is a temporary numeric UI; drag & drop comes later.', 'yak-term-order' ); ?></p>
				<?php wp_nonce_field( 'yto_save_term_order', 'yto_nonce' ); ?>
			</td>
		</tr>
		<?php
	}

	public function save_term_order( int $term_id, int $tt_id ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}
		if ( isset( $_POST['yak_term_order'] ) ) {
			if ( isset( $_POST['yto_nonce'] ) && ! wp_verify_nonce( $_POST['yto_nonce'], 'yto_save_term_order' ) ) {
				return;
			}
			$val = (int) $_POST['yak_term_order'];
			update_term_meta( $term_id, YTO_META_KEY, $val );
		}
	}

	private function capability(): string {
		$opts = get_settings();
		return $opts['capability'] ?: 'manage_categories';
	}
}
