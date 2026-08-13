<?php
defined( 'ABSPATH' ) || exit;

/**
 * Storage for quotes: a hidden custom post type (no default WP edit screen —
 * WAQ_Admin builds a purpose-made line-item UI instead) plus post meta for
 * line items and totals.
 *
 * Line items snapshot their price/tax at save time rather than referencing
 * live product data, so a quote's numbers never silently drift if a product
 * price changes after the quote was sent — same principle as a real invoice.
 */
class WAQ_Quote_Post_Type {

	const POST_TYPE = 'waq_quote';

	const META_CUSTOMER_NAME  = '_waq_customer_name';
	const META_CUSTOMER_EMAIL = '_waq_customer_email';
	const META_REQUEST_TEXT   = '_waq_request_text';
	const META_LINE_ITEMS     = '_waq_line_items';
	const META_QUOTE_NUMBER   = '_waq_quote_number';
	const META_TOTALS         = '_waq_totals';

	const OPTION_QUOTE_COUNTER = 'waq_quote_counter';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Quotes', 'ai-quotes-for-woocommerce' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * @return array{product_id:int, description:string, qty:float, unit_price:float, tax_rate:float|null}[]
	 */
	public static function get_line_items( $quote_id ) {
		$items = get_post_meta( $quote_id, self::META_LINE_ITEMS, true );
		return is_array( $items ) ? $items : array();
	}

	public static function save_line_items( $quote_id, array $items ) {
		$clean = array_values( array_map( array( __CLASS__, 'sanitize_line_item' ), $items ) );
		update_post_meta( $quote_id, self::META_LINE_ITEMS, $clean );
	}

	private static function sanitize_line_item( $item ) {
		return array(
			'product_id'  => isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0,
			'description' => isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '',
			'qty'         => isset( $item['qty'] ) ? (float) $item['qty'] : 1.0,
			'unit_price'  => isset( $item['unit_price'] ) ? (float) $item['unit_price'] : 0.0,
			'tax_rate'    => isset( $item['tax_rate'] ) && '' !== $item['tax_rate'] ? (float) $item['tax_rate'] : null,
		);
	}

	public static function get_totals( $quote_id ) {
		$totals = get_post_meta( $quote_id, self::META_TOTALS, true );
		return is_array( $totals ) ? $totals : array(
			'subtotal' => 0.0,
			'tax'      => 0.0,
			'total'    => 0.0,
		);
	}

	public static function save_totals( $quote_id, array $totals ) {
		update_post_meta( $quote_id, self::META_TOTALS, $totals );
	}

	public static function get_customer( $quote_id ) {
		return array(
			'name'  => get_post_meta( $quote_id, self::META_CUSTOMER_NAME, true ),
			'email' => get_post_meta( $quote_id, self::META_CUSTOMER_EMAIL, true ),
		);
	}

	public static function save_customer( $quote_id, $name, $email ) {
		update_post_meta( $quote_id, self::META_CUSTOMER_NAME, sanitize_text_field( $name ) );
		update_post_meta( $quote_id, self::META_CUSTOMER_EMAIL, sanitize_email( $email ) );
	}

	public static function get_quote_number( $quote_id ) {
		return get_post_meta( $quote_id, self::META_QUOTE_NUMBER, true );
	}

	/**
	 * Assigns a quote number the first time a quote is finalized. Called
	 * once per quote (checks for an existing number first) so re-saving a
	 * finalized quote doesn't burn a new number each time.
	 */
	public static function assign_quote_number( $quote_id ) {
		$existing = self::get_quote_number( $quote_id );
		if ( $existing ) {
			return $existing;
		}
		$counter = (int) get_option( self::OPTION_QUOTE_COUNTER, 0 ) + 1;
		update_option( self::OPTION_QUOTE_COUNTER, $counter );
		$number = WAQ_Settings::get_quote_prefix() . str_pad( (string) $counter, 4, '0', STR_PAD_LEFT );
		update_post_meta( $quote_id, self::META_QUOTE_NUMBER, $number );
		return $number;
	}
}
