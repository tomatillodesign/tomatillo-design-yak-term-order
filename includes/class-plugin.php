<?php
namespace Yak_Term_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {

	private static ?Plugin $instance = null;
	private array $cfg;

	public static function instance( array $cfg ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $cfg );
		}
		return self::$instance;
	}

	private function __construct( array $cfg ) {
		$this->cfg = $cfg;

		// Settings first so options are available to other modules on admin_init.
		if ( is_admin() ) {
			new Settings( $cfg );
            new Inline_Order( $cfg ); // <— add this to embed on edit-tags.php
		}

		new Autosort( $cfg ); // global ordering via termmeta (front + admin, per settings)
		new Admin_UI( $cfg ); // minimal numeric field on term screens (for enabled taxonomies)
        
	}

	public function cfg( string $key, $default = null ) {
		return $this->cfg[ $key ] ?? $default;
	}
}
