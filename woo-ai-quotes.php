<?php
/**
 * Plugin Name:       AI Quotes for WooCommerce
 * Plugin URI:         https://github.com/abid/woo-ai-quotes
 * Description:        Draft a branded, calculated quote from a plain-language request against your real product catalog — AI drafts the line items, the plugin computes every number.
 * Version:            0.1.3
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * WC requires at least: 8.0
 * Author:             Abid
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        woo-ai-quotes
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WAQ_VERSION', '0.1.3' );
define( 'WAQ_PLUGIN_FILE', __FILE__ );
define( 'WAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bail early and show an admin notice if WooCommerce isn't active, rather than
 * fatal-erroring on missing WC classes.
 */
function waq_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'AI Quotes for WooCommerce requires WooCommerce to be installed and active.', 'woo-ai-quotes' ) .
		'</p></div>';
}

/**
 * Bail with an admin notice if the bundled dompdf library hasn't been
 * installed (composer install). Distributed release zips ship it built-in;
 * this only fires for someone running from a raw git checkout.
 */
function waq_vendor_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'AI Quotes for WooCommerce is missing its PDF library. Run `composer install` in the plugin directory, or install from a release zip instead of a raw checkout.', 'woo-ai-quotes' ) .
		'</p></div>';
}

function waq_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'waq_woocommerce_missing_notice' );
		return;
	}

	if ( ! file_exists( WAQ_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		add_action( 'admin_notices', 'waq_vendor_missing_notice' );
		return;
	}
	require_once WAQ_PLUGIN_DIR . 'vendor/autoload.php';

	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-settings.php';
	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-quote-post-type.php';
	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-calculator.php';
	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-ai-drafter.php';
	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-pdf.php';
	require_once WAQ_PLUGIN_DIR . 'includes/class-waq-admin.php';

	WAQ_Settings::init();
	WAQ_Quote_Post_Type::init();
	WAQ_Admin::init();
}
add_action( 'plugins_loaded', 'waq_init' );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility. This plugin
 * stores quotes as its own post type and never touches WooCommerce orders,
 * but declaring compatibility explicitly avoids WooCommerce's "incompatible
 * plugin" admin warning.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
