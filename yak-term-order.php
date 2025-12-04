<?php
/**
 * Plugin Name:       Tomatillo Design ~ Yak Term Order
 * Description:       Drag-and-drop ordering for taxonomy terms AND posts/custom post types. FacetWP-compatible. Non-destructive storage.
 * Version:           1.2.0
 * Author:            Chris Liu-Beers, Tomatillo Design
 * Author URI:        https://tomatillodesign.com
 * Text Domain:       yak-term-order
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * License:           GPL-2.0-or-later
 *
 * @package Yak_Term_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -------------------------------------------------------------------------
 * Constants
 * -------------------------------------------------------------------------
 */

// Absolute path to this plugin file.
define( 'YTO_PLUGIN_FILE', __FILE__ );

// Directory path and URL.
define( 'YTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Plugin version (bump for cache-busting and migrations).
define( 'YTO_VERSION', '1.2.0' );

// Term meta key used to store sibling order (integer).
define( 'YTO_META_KEY', '_yak_term_order' );

// Option key used for plugin settings (non-destructive; kept across de/activation).
define( 'YTO_OPTION_KEY', 'yak_term_order_settings' );

// Query arg to opt-out of autosort for a specific get_terms() call.
define( 'YTO_IGNORE_ARG', 'ignore_term_order' );

// Default capability to manage ordering UI.
define( 'YTO_CAP', 'manage_categories' );

// Internal cache key registry (used so we can clear only our own caches on deactivation).
define( 'YTO_CACHE_REGISTRY_OPTION', 'yak_term_order_cache_keys' );

// Debug mode: uncomment to enable detailed logging to debug.log.
// define( 'YTO_DEBUG', true );



/**
 * -------------------------------------------------------------------------
 * Autoloader (PSR-4-ish for the Yak_Term_Order\ namespace)
 * -------------------------------------------------------------------------
 *
 * Maps:
 *   Yak_Term_Order\Class_Name         -> includes/class-class-name.php
 *   Yak_Term_Order\Sub\Class_Name     -> includes/sub/class-class-name.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Yak_Term_Order\\';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );

		// Convert namespace separators to paths.
		$relative_path = str_replace( '\\', '/', $relative );

		// Convert "Class_Name" to "class-class-name.php".
		$basename = 'class-' . strtolower( str_replace( '_', '-', basename( $relative_path ) ) ) . '.php';

		// Build final path.
		$dir  = dirname( $relative_path );
		$path = ( '.' === $dir ) ? 'includes/' . $basename : 'includes/' . $dir . '/' . $basename;

		$full = YTO_PLUGIN_DIR . $path;

		if ( is_readable( $full ) ) {
			require_once $full;
		}
	}
);

/**
 * -------------------------------------------------------------------------
 * Internationalization
 * -------------------------------------------------------------------------
 */
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'yak-term-order', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * -------------------------------------------------------------------------
 * Activation / Deactivation
 * -------------------------------------------------------------------------
 *
 * Activation:
 *  - Seed defaults (non-destructive).
 *  - NOTE: We do NOT alter core DB schema (term-meta backend only).
 *
 * Deactivation:
 *  - IMPORTANT: WordPress reverts to default term ordering automatically
 *    because all autosort filters live inside this plugin. When the plugin
 *    is inactive, those hooks do not run, so WP uses its native order again.
 *  - Clear ONLY plugin-scoped caches (if any) to avoid "sticky" ordering.
 *  - Leave settings and term meta in place for a smooth re-activation later.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		$defaults = array(
			'autosort_enabled'       => true,    // apply globally across selected taxonomies.
			'admin_autosort'         => true,    // also sort admin list tables & pickers.
			'taxonomies'             => array(), // empty = disabled until user selects.
			'secondary_orderby'      => 'name',  // tiebreaker ('name' or 'term_id').
			'backend'                => 'meta',  // locked to 'meta' (no schema changes).
			'capability'             => YTO_CAP, // who may reorder.
			'post_types'             => array(), // post types with ordering enabled.
			'post_autosort_frontend' => false,   // auto-order posts by menu_order on front end.
		);

		$current = get_option( YTO_OPTION_KEY );

		if ( ! is_array( $current ) ) {
			add_option( YTO_OPTION_KEY, $defaults, '', false );
		} else {
			update_option( YTO_OPTION_KEY, array_merge( $defaults, $current ), false );
		}

		// Initialize an empty cache registry so deactivation can clear just our keys.
		if ( false === get_option( YTO_CACHE_REGISTRY_OPTION, false ) ) {
			add_option( YTO_CACHE_REGISTRY_OPTION, array(), '', false );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		// 1) Explicitly clear only Yak Term Order caches (if any were registered).
		$keys = get_option( YTO_CACHE_REGISTRY_OPTION, array() );
		if ( is_array( $keys ) && ! empty( $keys ) ) {
			foreach ( $keys as $cache_key ) {
				// Object cache first.
				wp_cache_delete( $cache_key, 'yak_term_order' );
				// Then transient API key if it matches our naming (defensive).
				delete_transient( $cache_key );
			}
		}

		// 2) Soft-signal for any loaded classes to purge ephemeral state.
		// Note: on deactivation, only this file is guaranteed to be loaded.
		// This action is here primarily for integration tests / future use.
		do_action( 'yak_term_order/deactivated' );

		/**
		 * IMPORTANT:
		 * We do NOT modify saved settings on deactivation. We want a smooth
		 * "resume where you left off" on reactivation. And since all autosort
		 * behavior is attached via runtime hooks in our classes, simply being
		 * inactive means those hooks are absent -> WP default ordering applies.
		 */
	}
);

/**
 * -------------------------------------------------------------------------
 * Bootstrap
 * -------------------------------------------------------------------------
 *
 * The Plugin orchestrator wires up:
 *  - Autosort: filters `terms_clauses` / `get_terms_orderby` to sort by term meta
 *  - Admin UI: drag/drop screen + AJAX save (cap from settings, default YTO_CAP)
 *  - REST: endpoints for saving/reindexing
 *  - Settings: choose taxonomies, toggles, capability
 *
 * If the plugin is inactive, none of these hooks are loaded, and WordPress
 * falls back to its default ordering automatically.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		// Load non-class helpers (optional).
		$helpers = YTO_PLUGIN_DIR . 'includes/helpers.php';
		if ( is_readable( $helpers ) ) {
			require_once $helpers;
		}

		// Initialize core plugin if the orchestrator is present.
		if ( class_exists( 'Yak_Term_Order\\Plugin' ) ) {
			Yak_Term_Order\Plugin::instance(
				array(
					'meta_key'        => YTO_META_KEY,
					'option_key'      => YTO_OPTION_KEY,
					'ignore_arg'      => YTO_IGNORE_ARG,
					'cache_registry'  => YTO_CACHE_REGISTRY_OPTION,
					'version'         => YTO_VERSION,
					'default_cap'     => YTO_CAP,
					'plugin_file'     => YTO_PLUGIN_FILE,
					'plugin_dir'      => YTO_PLUGIN_DIR,
					'plugin_url'      => YTO_PLUGIN_URL,
					'text_domain'     => 'yak-term-order',
				)
			);
		} else {
			// Soft failure notice in admin if class missing.
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>';
						echo esc_html__( 'Yak Term Order: core class not found. Please ensure includes/class-plugin.php exists.', 'yak-term-order' );
						echo '</p></div>';
					}
				}
			);
		}
	}
);

/**
 * -------------------------------------------------------------------------
 * Public helpers
 * -------------------------------------------------------------------------
 */

/**
 * Check if autosort is enabled for a taxonomy (based on stored settings).
 *
 * @param string $taxonomy Taxonomy slug.
 * @return bool
 */
function yto_autosort_enabled_for( string $taxonomy ): bool {
	$opts = get_option( YTO_OPTION_KEY );
	if ( empty( $opts ) || empty( $opts['autosort_enabled'] ) ) {
		return false;
	}
	if ( empty( $opts['taxonomies'] ) || ! is_array( $opts['taxonomies'] ) ) {
		return false;
	}
	return in_array( $taxonomy, $opts['taxonomies'], true );
}

/**
 * Register a plugin-scoped cache key so we can clear it on deactivation.
 * (Optional utility; your Autosort/Admin classes can call this when caching.)
 *
 * @param string $cache_key Cache key to register.
 * @return void
 */
function yto_register_cache_key( string $cache_key ): void {
	if ( '' === $cache_key ) {
		return;
	}
	$keys = get_option( YTO_CACHE_REGISTRY_OPTION, array() );
	if ( ! is_array( $keys ) ) {
		$keys = array();
	}
	if ( ! in_array( $cache_key, $keys, true ) ) {
		$keys[] = $cache_key;
		update_option( YTO_CACHE_REGISTRY_OPTION, $keys, false );
	}
}

/**
 * -------------------------------------------------------------------------
 * Dev hook anchors (documented in README)
 * -------------------------------------------------------------------------
 *
 * do_action( 'yak_term_order/updated', $taxonomy, $parent_id, $old_ids, $new_ids, $user_id );
 * do_action( 'yak_term_order/deactivated' );
 *
 * apply_filters( 'yak_term_order/autosort_enabled', $enabled, $taxonomy, $context );
 * apply_filters( 'yak_term_order/ignore', $ignore, $args );
 * apply_filters( 'yak_term_order/secondary_orderby', $orderby_sql, $taxonomy );
 */
