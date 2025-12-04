<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Settings {

	private array $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_settings_page(): void {
		add_options_page(
			__( 'Yak Term Order', 'yak-term-order' ),
			__( 'Yak Term Order', 'yak-term-order' ),
			$this->capability(),
			'yak-term-order',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'yto_settings',
			YTO_OPTION_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);

		add_settings_section(
			'yto_main',
			__( 'Term Ordering Settings', 'yak-term-order' ),
			function () {
				echo '<p class="description">' . esc_html__( 'Choose which taxonomies use custom ordering and where it applies.', 'yak-term-order' ) . '</p>';
			},
			'yto_settings'
		);

		add_settings_field(
			'autosort_enabled',
			__( 'Front-end autosort', 'yak-term-order' ),
			[ $this, 'field_autosort_enabled' ],
			'yto_settings',
			'yto_main'
		);

		add_settings_field(
			'admin_autosort',
			__( 'Admin autosort', 'yak-term-order' ),
			[ $this, 'field_admin_autosort' ],
			'yto_settings',
			'yto_main'
		);

		add_settings_field(
			'taxonomies',
			__( 'Enabled taxonomies', 'yak-term-order' ),
			[ $this, 'field_taxonomies' ],
			'yto_settings',
			'yto_main'
		);

		add_settings_field(
			'secondary_orderby',
			__( 'Tiebreaker', 'yak-term-order' ),
			[ $this, 'field_secondary' ],
			'yto_settings',
			'yto_main'
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yak-term-order' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Yak Term Order', 'yak-term-order' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'yto_settings' );
				do_settings_sections( 'yto_settings' );
				submit_button( __( 'Save Changes', 'yak-term-order' ) );
				?>
			</form>
		</div>
		<?php
	}

	// ——— Fields ———

	public function field_autosort_enabled(): void {
		$opts = get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( YTO_OPTION_KEY ); ?>[autosort_enabled]" value="1" <?php checked( ! empty( $opts['autosort_enabled'] ) ); ?> />
			<?php esc_html_e( 'Apply custom order on the front end', 'yak-term-order' ); ?>
		</label>
		<?php
	}

	public function field_admin_autosort(): void {
		$opts = get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( YTO_OPTION_KEY ); ?>[admin_autosort]" value="1" <?php checked( ! empty( $opts['admin_autosort'] ) ); ?> />
			<?php esc_html_e( 'Apply custom order in admin lists & pickers', 'yak-term-order' ); ?>
		</label>
		<?php
	}

	public function field_taxonomies(): void {
		$opts  = get_settings();
		$vals  = is_array( $opts['taxonomies'] ) ? $opts['taxonomies'] : [];
		$choices = $this->taxonomy_choices();

		if ( empty( $choices ) ) {
			echo '<p>' . esc_html__( 'No public taxonomies found.', 'yak-term-order' ) . '</p>';
			return;
		}

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Enabled taxonomies', 'yak-term-order' ) . '</legend>';
		foreach ( $choices as $slug => $label ) :
			?>
			<label style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="<?php echo esc_attr( YTO_OPTION_KEY ); ?>[taxonomies][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $vals, true ) ); ?> />
				<?php echo esc_html( $label . " ($slug)" ); ?>
			</label>
			<?php
		endforeach;
		echo '</fieldset>';
	}

	public function field_secondary(): void {
		$opts = get_settings();
		$val  = $opts['secondary_orderby'] === 'term_id' ? 'term_id' : 'name';
		?>
		<select name="<?php echo esc_attr( YTO_OPTION_KEY ); ?>[secondary_orderby]">
			<option value="name" <?php selected( $val, 'name' ); ?>><?php esc_html_e( 'Name (A→Z)', 'yak-term-order' ); ?></option>
			<option value="term_id" <?php selected( $val, 'term_id' ); ?>><?php esc_html_e( 'ID (oldest→newest)', 'yak-term-order' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Used when two siblings have the same manual order or no value set.', 'yak-term-order' ); ?></p>
		<?php
	}

	// ——— Sanitization ———

	public function sanitize( $input ): array {
		$defaults = get_settings();

		$out = [
			'autosort_enabled'  => ! empty( $input['autosort_enabled'] ) ? 1 : 0,
			'admin_autosort'    => ! empty( $input['admin_autosort'] ) ? 1 : 0,
			'taxonomies'        => [],
			'secondary_orderby' => ( isset( $input['secondary_orderby'] ) && 'term_id' === $input['secondary_orderby'] ) ? 'term_id' : 'name',
			'backend'           => 'meta',
			'capability'        => $defaults['capability'],
		];

		if ( ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			$allowed = array_keys( $this->taxonomy_choices() );
			$san     = array_map( 'sanitize_key', $input['taxonomies'] );
			$out['taxonomies'] = array_values( array_intersect( $san, $allowed ) );
		}

		return $out;
	}

	// ——— Helpers ———

	private function capability(): string {
		$opts = get_settings();
		return $opts['capability'] ?: 'manage_categories';
	}

	private function taxonomy_choices(): array {
		$choices = [];
		$taxes = get_taxonomies( [ 'public' => true ], 'objects' );
		foreach ( $taxes as $tax ) {
			$choices[ $tax->name ] = $tax->labels->singular_name ?: $tax->label;
		}
		// Ensure your target is visible even if it's not public (edge case).
		if ( taxonomy_exists( 'ob_algorithm_category' ) && ! isset( $choices['ob_algorithm_category'] ) ) {
			$tax = get_taxonomy( 'ob_algorithm_category' );
			$choices['ob_algorithm_category'] = $tax ? ( $tax->labels->singular_name ?: $tax->label ) : 'OB Algorithm Category';
		}
		ksort( $choices, SORT_NATURAL | SORT_FLAG_CASE );
		return $choices;
	}
}
