<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds an "AI Quotes" tab under WooCommerce → Settings: the AI provider/key
 * used to draft line items, and the company details/branding printed on
 * every generated quote. Unlike a single-business tool, none of this can be
 * hardcoded — every install belongs to a different company.
 */
class WAQ_Settings {

	const OPTION_AI_PROVIDER       = 'waq_ai_provider';
	const OPTION_OPENAI_KEY        = 'waq_openai_api_key';
	const OPTION_OPENAI_MODEL      = 'waq_openai_model';
	const OPTION_ANTHROPIC_KEY     = 'waq_anthropic_api_key';
	const OPTION_ANTHROPIC_MODEL   = 'waq_anthropic_model';
	const OPTION_COMPANY_NAME      = 'waq_company_name';
	const OPTION_COMPANY_ADDRESS   = 'waq_company_address';
	const OPTION_COMPANY_PHONE     = 'waq_company_phone';
	const OPTION_COMPANY_EMAIL     = 'waq_company_email';
	const OPTION_COMPANY_LOGO_URL  = 'waq_company_logo_url';
	const OPTION_FALLBACK_TAX_RATE = 'waq_fallback_tax_rate';
	const OPTION_PAYMENT_DETAILS   = 'waq_payment_details';
	const OPTION_QUOTE_FOOTER      = 'waq_quote_footer';
	const OPTION_QUOTE_PREFIX      = 'waq_quote_number_prefix';

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 55 );
		add_action( 'woocommerce_settings_tabs_waq', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_update_options_waq', array( __CLASS__, 'save' ) );
	}

	public static function add_tab( $tabs ) {
		$tabs['waq'] = __( 'AI Quotes', 'woo-ai-quotes' );
		return $tabs;
	}

	private static function fields() {
		return array(
			array(
				'title' => __( 'AI provider', 'woo-ai-quotes' ),
				'type'  => 'title',
				'desc'  => __( 'Bring your own API key — quotes are drafted using your account, so usage is billed to you directly by the provider, not marked up by this plugin.', 'woo-ai-quotes' ),
				'id'    => 'waq_section_ai_title',
			),
			array(
				'title'    => __( 'Provider', 'woo-ai-quotes' ),
				'id'       => self::OPTION_AI_PROVIDER,
				'type'     => 'select',
				'options'  => array(
					'openai'    => __( 'OpenAI', 'woo-ai-quotes' ),
					'anthropic' => __( 'Anthropic (Claude)', 'woo-ai-quotes' ),
				),
				'default'  => 'openai',
			),
			array(
				'title'    => __( 'OpenAI API key', 'woo-ai-quotes' ),
				'id'       => self::OPTION_OPENAI_KEY,
				'type'     => 'password',
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'OpenAI model', 'woo-ai-quotes' ),
				'id'       => self::OPTION_OPENAI_MODEL,
				'type'     => 'text',
				'default'  => 'gpt-4o-mini',
				'css'      => 'width:250px;',
			),
			array(
				'title'    => __( 'Anthropic API key', 'woo-ai-quotes' ),
				'id'       => self::OPTION_ANTHROPIC_KEY,
				'type'     => 'password',
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'Anthropic model', 'woo-ai-quotes' ),
				'id'       => self::OPTION_ANTHROPIC_MODEL,
				'type'     => 'text',
				'default'  => 'claude-haiku-4-5-20251001',
				'css'      => 'width:250px;',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'waq_section_ai_end',
			),
			array(
				'title' => __( 'Company details', 'woo-ai-quotes' ),
				'type'  => 'title',
				'desc'  => __( 'Printed on every generated quote PDF.', 'woo-ai-quotes' ),
				'id'    => 'waq_section_company_title',
			),
			array(
				'title'    => __( 'Company name', 'woo-ai-quotes' ),
				'id'       => self::OPTION_COMPANY_NAME,
				'type'     => 'text',
				'default'  => get_bloginfo( 'name' ),
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'Address', 'woo-ai-quotes' ),
				'id'       => self::OPTION_COMPANY_ADDRESS,
				'type'     => 'textarea',
				'css'      => 'width:400px; height:60px;',
			),
			array(
				'title'    => __( 'Phone', 'woo-ai-quotes' ),
				'id'       => self::OPTION_COMPANY_PHONE,
				'type'     => 'text',
				'css'      => 'width:250px;',
			),
			array(
				'title'    => __( 'Email', 'woo-ai-quotes' ),
				'id'       => self::OPTION_COMPANY_EMAIL,
				'type'     => 'email',
				'default'  => get_bloginfo( 'admin_email' ),
				'css'      => 'width:250px;',
			),
			array(
				'title'    => __( 'Logo URL', 'woo-ai-quotes' ),
				'id'       => self::OPTION_COMPANY_LOGO_URL,
				'type'     => 'text',
				'desc'     => __( 'Paste a Media Library image URL (Media → Add New, then copy the file URL). Leave blank for a text-only header.', 'woo-ai-quotes' ),
				'css'      => 'width:400px;',
			),
			array(
				'title'    => __( 'Fallback tax rate (%)', 'woo-ai-quotes' ),
				'id'       => self::OPTION_FALLBACK_TAX_RATE,
				'type'     => 'number',
				'default'  => '0',
				'desc'     => __( 'Used only for line items not tied to a real product (those use your existing WooCommerce tax settings instead).', 'woo-ai-quotes' ),
				'css'      => 'width:100px;',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),
			array(
				'title'    => __( 'Payment / bank details', 'woo-ai-quotes' ),
				'id'       => self::OPTION_PAYMENT_DETAILS,
				'type'     => 'textarea',
				'desc'     => __( 'Optional — shown in the quote footer if filled in.', 'woo-ai-quotes' ),
				'css'      => 'width:400px; height:75px;',
			),
			array(
				'title'    => __( 'Quote footer / terms', 'woo-ai-quotes' ),
				'id'       => self::OPTION_QUOTE_FOOTER,
				'type'     => 'textarea',
				'desc'     => __( 'E.g. validity period, terms, thank-you note.', 'woo-ai-quotes' ),
				'css'      => 'width:400px; height:75px;',
			),
			array(
				'title'    => __( 'Quote number prefix', 'woo-ai-quotes' ),
				'id'       => self::OPTION_QUOTE_PREFIX,
				'type'     => 'text',
				'default'  => 'Q-',
				'css'      => 'width:100px;',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'waq_section_company_end',
			),
		);
	}

	public static function render() {
		woocommerce_admin_fields( self::fields() );
	}

	public static function save() {
		woocommerce_update_options( self::fields() );
	}

	public static function get_provider() {
		return get_option( self::OPTION_AI_PROVIDER, 'openai' );
	}

	public static function get_api_key( $provider = null ) {
		$provider = $provider ?: self::get_provider();
		return 'anthropic' === $provider
			? get_option( self::OPTION_ANTHROPIC_KEY, '' )
			: get_option( self::OPTION_OPENAI_KEY, '' );
	}

	public static function get_model( $provider = null ) {
		$provider = $provider ?: self::get_provider();
		return 'anthropic' === $provider
			? get_option( self::OPTION_ANTHROPIC_MODEL, 'claude-haiku-4-5-20251001' )
			: get_option( self::OPTION_OPENAI_MODEL, 'gpt-4o-mini' );
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

	public static function is_configured() {
		return '' !== self::get_api_key();
	}
}
