<?php
/*
 * Plugin Name:          Ecomail
 * Description:          Official Ecomail integration for WordPress and WooCommerce
 * Version:              2.1.6
 * Requires PHP:         7.4.0
 * Requires at least:    5.3.0
 * Author:               ECOMAIL.CZ
 * Author URI:           https://ecomail.cz/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          ecomail
 * Domain Path:          /languages
 * WC requires at least: 4.5
 * WC tested up to:      8.0
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Ecomail\Plugin;
use EcomailDeps\DI\Container;
use EcomailDeps\DI\ContainerBuilder;

if ( ! defined( 'ECOMAIL_MIN_PHP_VERSION' ) ) {
	define( 'ECOMAIL_MIN_PHP_VERSION', '7.4.0' );
}

/**
 * Singleton instance function. We will not use a global at all as that defeats the purpose of a singleton
 * and is a bad design overall
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @return Plugin
 * @throws Exception
 */
function ecomail(): Plugin {
	return ecomail_container()->get( Plugin::class );
}

/**
 * This container singleton enables you to setup unit testing by passing an environment file to map classes in Dice
 * @return Container
 * @throws Exception
 */
function ecomail_container(): Container {
	static $container;

	if ( empty( $container ) ) {
		$is_production    = ! WP_DEBUG;
		$file_data        = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		$definition       = require_once __DIR__ . '/config.php';
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinitions( $definition );

		if ( $is_production ) {
			$containerBuilder->enableCompilation( WP_CONTENT_DIR . '/cache/' . dirname( plugin_basename( __FILE__ ) ) . '/' . $file_data['version'], 'EcomailCompiledContainer' );
		}

		$container = $containerBuilder->build();
	}

	return $container;
}

function ecomail_activate( $network_wide ) {
	ecomail()->activate( $network_wide );
}

function ecomail_deactivate( $network_wide ) {
	ecomail()->deactivate( $network_wide );
}

function ecomail_uninstall() {
	ecomail()->uninstall();
}

/**
 * Error for older php
 */
function ecomail_php_upgrade_notice() {
	$info = get_plugin_data( __FILE__ );

	echo sprintf(
		__( '<div class="error notice"><p>Opps! %s requires a minimum PHP version of %s. Your current version is: %s. Please contact your host to upgrade.</p></div>', 'ecomail' ),
		$info['Name'],
		ECOMAIL_MIN_PHP_VERSION,
		PHP_VERSION
	);
}

/**
 * Error if vendors autoload is missing
 */
function ecomail_php_vendor_missing() {
	$info = get_plugin_data( __FILE__ );

	echo sprintf(
		__( '<div class="error notice"><p>Opps! %s is corrupted it seems, please re-install the plugin.</p></div>', 'ecomail' ),
		$info['Name']
	);
}


/**
 * Load plugin textdomain.
 */
function ecomail_load_textdomain() {
	load_plugin_textdomain( 'ecomail', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ecomail_load_textdomain' );

/**
 * WooCommerce not active notice
 */
function ecomail_woocommerce_not_active() {
	?>
	<div class="error notice">
		<p><?php
			_e( 'This plugin requires WooCommerce. Please install and activate it first.', 'ecomail' ); ?></p>
	</div>
	<?php
}

/**
 * Check if plugin is active
 */
function ecomail_plugin_is_active( $plugin ) {
	if ( is_multisite() ) {
		$plugins = get_site_option('active_sitewide_plugins');
		if ( isset( $plugins[ $plugin ] ) ) {
			return true;
		}
	}

	if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
		return true;
	}

	return false;
}

if ( version_compare( PHP_VERSION, ECOMAIL_MIN_PHP_VERSION ) < 0 ) {
	add_action( 'admin_notices', 'ecomail_php_upgrade_notice' );
} elseif ( ! ecomail_plugin_is_active('woocommerce/woocommerce.php') ) {
	add_action( 'admin_notices', 'ecomail_woocommerce_not_active' );
} else {
	$deps_loaded   = false;
	$vendor_loaded = false;

	$deps = array_filter( array( __DIR__ . '/deps/scoper-autoload.php', __DIR__ . '/deps/autoload.php' ), function ( $path ) {
		return file_exists( $path );
	} );

	foreach ( $deps as $dep ) {
		include_once $dep;
		$deps_loaded = true;
	}

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		include_once __DIR__ . '/vendor/autoload.php';
		$vendor_loaded = true;
	}

	if ( $deps_loaded && $vendor_loaded ) {
		add_action( 'plugins_loaded', 'ecomail', 11 );
		register_activation_hook( __FILE__, 'ecomail_activate' );
		register_deactivation_hook( __FILE__, 'ecomail_deactivate' );
		register_uninstall_hook( __FILE__, 'ecomail_uninstall' );
	} else {
		add_action( 'admin_notices', 'ecomail_php_vendor_missing' );
	}
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );
