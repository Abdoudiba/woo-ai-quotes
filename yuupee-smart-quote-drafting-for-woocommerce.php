<?php
/**
 * Plugin Name:       Yuupee Smart Quote Drafting for WooCommerce
 * Plugin URI:         https://github.com/Abdoudiba/yuupee-smart-quote-drafting-for-woocommerce
 * Description:        Draft a branded, calculated quote from a plain-language request against your real product catalog — AI drafts the line items, the plugin computes every number.
 * Version:            0.4.1
 * Requires at least:  7.0
 * Requires PHP:       7.4
 * Requires Plugins:   woocommerce
 * WC requires at least: 8.0
 * Author:             Abid
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        yuupee-smart-quote-drafting-for-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'YSQD_VERSION', '0.4.1' );
define( 'YSQD_PLUGIN_FILE', __FILE__ );
define( 'YSQD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YSQD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bail early and show an admin notice if WooCommerce isn't active, rather than
 * fatal-erroring on missing WC classes.
 */
function ysqd_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Yuupee Smart Quote Drafting for WooCommerce requires WooCommerce to be installed and active.', 'yuupee-smart-quote-drafting-for-woocommerce' ) .
		'</p></div>';
}

/**
 * Bail with an admin notice if the bundled dompdf library hasn't been
 * installed (composer install). Distributed release zips ship it built-in;
 * this only fires for someone running from a raw git checkout.
 */
function ysqd_vendor_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Yuupee Smart Quote Drafting for WooCommerce is missing its PDF library. Run `composer install` in the plugin directory, or install from a release zip instead of a raw checkout.', 'yuupee-smart-quote-drafting-for-woocommerce' ) .
		'</p></div>';
}

function ysqd_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ysqd_woocommerce_missing_notice' );
		return;
	}

	if ( ! file_exists( YSQD_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		add_action( 'admin_notices', 'ysqd_vendor_missing_notice' );
		return;
	}
	require_once YSQD_PLUGIN_DIR . 'vendor/autoload.php';

	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-settings.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-quote-post-type.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-calculator.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-ai-drafter.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-pdf.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-admin.php';
	require_once YSQD_PLUGIN_DIR . 'includes/class-ysqd-rest.php';

	YSQD_Settings::init();
	YSQD_Quote_Post_Type::init();
	YSQD_Admin::init();
	YSQD_REST::init();
}
add_action( 'plugins_loaded', 'ysqd_init' );

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
