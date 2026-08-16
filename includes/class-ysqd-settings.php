<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds an "AI Quotes" tab under WooCommerce → Settings for the company
 * details/branding printed on every generated quote. Unlike a
 * single-business tool, none of this can be hardcoded — every install
 * belongs to a different company. The AI provider itself is configured
 * separately by the site owner under Settings → Connectors (WP AI Client,
 * core since WordPress 7.0) — see YSQD_AI_Drafter::is_available().
 */
class YSQD_Settings {

	const OPTION_COMPANY_NAME      = 'ysqd_company_name';
	const OPTION_COMPANY_ADDRESS   = 'ysqd_company_address';
	const OPTION_COMPANY_PHONE     = 'ysqd_company_phone';
	const OPTION_COMPANY_EMAIL     = 'ysqd_company_email';
	const OPTION_COMPANY_LOGO_URL  = 'ysqd_company_logo_url';
	const OPTION_FALLBACK_TAX_RATE = 'ysqd_fallback_tax_rate';
	const OPTION_PAYMENT_DETAILS   = 'ysqd_payment_details';
	const OPTION_QUOTE_FOOTER      = 'ysqd_quote_footer';
	const OPTION_QUOTE_PREFIX      = 'ysqd_quote_number_prefix';

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 55 );
		add_action( 'woocommerce_settings_tabs_ysqd', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_update_options_ysqd', array( __CLASS__, 'save' ) );
	}

	public static function add_tab( $tabs ) {
		$tabs['ysqd'] = __( 'AI Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' );
		return $tabs;
	}

	private static function fields() {
		return array(
			array(
				'title' => __( 'Company details', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Printed on every generated quote PDF.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'    => 'ysqd_section_company_title',
			),
			array(
				'title'    => __( 'Company name', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_COMPANY_NAME,
				'type'     => 'text',
				'default'  => get_bloginfo( 'name' ),
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'Address', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_COMPANY_ADDRESS,
				'type'     => 'textarea',
				'css'      => 'width:400px; height:60px;',
			),
			array(
				'title'    => __( 'Phone', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_COMPANY_PHONE,
				'type'     => 'text',
				'css'      => 'width:250px;',
			),
			array(
				'title'    => __( 'Email', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_COMPANY_EMAIL,
				'type'     => 'email',
				'default'  => get_bloginfo( 'admin_email' ),
				'css'      => 'width:250px;',
			),
			array(
				'title'    => __( 'Logo URL', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_COMPANY_LOGO_URL,
				'type'     => 'text',
				'desc'     => __( 'Paste a Media Library image URL (Media → Add New, then copy the file URL). Leave blank for a text-only header.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'Fallback tax rate (%)', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_FALLBACK_TAX_RATE,
				'type'     => 'number',
				'default'  => '0',
				'desc'     => __( 'Used only for line items not tied to a real product (those use your existing WooCommerce tax settings instead).', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'css'      => 'width:100px;',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),
			array(
				'title'    => __( 'Payment / bank details', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_PAYMENT_DETAILS,
				'type'     => 'textarea',
				'desc'     => __( 'Optional — shown in the quote footer if filled in.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'css'      => 'width:400px; height:75px;',
			),
			array(
				'title'    => __( 'Quote footer / terms', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_QUOTE_FOOTER,
				'type'     => 'textarea',
				'desc'     => __( 'E.g. validity period, terms, thank-you note.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'css'      => 'width:400px; height:75px;',
			),
			array(
				'title'    => __( 'Quote number prefix', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				'id'       => self::OPTION_QUOTE_PREFIX,
				'type'     => 'text',
				'default'  => 'Q-',
				'css'      => 'width:100px;',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ysqd_section_company_end',
			),
		);
	}

	public static function render() {
		woocommerce_admin_fields( self::fields() );
	}

	public static function save() {
		woocommerce_update_options( self::fields() );
	}

	public static function get_company() {
		return array(
			'name'    => get_option( self::OPTION_COMPANY_NAME, get_bloginfo( 'name' ) ),
			'address' => get_option( self::OPTION_COMPANY_ADDRESS, '' ),
			'phone'   => get_option( self::OPTION_COMPANY_PHONE, '' ),
			'email'   => get_option( self::OPTION_COMPANY_EMAIL, get_bloginfo( 'admin_email' ) ),
			'logo'    => get_option( self::OPTION_COMPANY_LOGO_URL, '' ),
		);
	}

	public static function get_fallback_tax_rate() {
		return (float) get_option( self::OPTION_FALLBACK_TAX_RATE, 0 );
	}

	public static function get_payment_details() {
		return get_option( self::OPTION_PAYMENT_DETAILS, '' );
	}

	public static function get_quote_footer() {
		return get_option( self::OPTION_QUOTE_FOOTER, '' );
	}

	public static function get_quote_prefix() {
		return get_option( self::OPTION_QUOTE_PREFIX, 'Q-' );
	}
}
