<?php
defined( 'ABSPATH' ) || exit;

/**
 * Computes quote totals from line items. This is the one piece of the
 * plugin the AI is never allowed near — every number here comes from PHP
 * arithmetic against real WooCommerce tax data (or the fallback rate for
 * items with no linked product), never from a model's output.
 */
class WAQ_Calculator {

	/**
	 * @param array $line_items As returned by WAQ_Quote_Post_Type::get_line_items().
	 * @return array{subtotal:float, tax:float, total:float}
	 */
	public static function calculate( array $line_items ) {
		$subtotal = 0.0;
		$tax      = 0.0;

		foreach ( $line_items as $item ) {
			$qty        = (float) ( $item['qty'] ?? 0 );
			$unit_price = (float) ( $item['unit_price'] ?? 0 );
			$line_total = $qty * $unit_price;

			$subtotal += $line_total;
			$tax      += $line_total * ( self::resolve_tax_rate_percent( $item ) / 100 );
		}

		$subtotal = round( $subtotal, 2 );
		$tax      = round( $tax, 2 );

		return array(
			'subtotal' => $subtotal,
			'tax'      => $tax,
			'total'    => round( $subtotal + $tax, 2 ),
		);
	}

	/**
	 * Priority: an explicit per-line override (set by a rep editing the
	 * draft) > the linked product's real WooCommerce tax rate > the plugin's
	 * fallback rate for free-text items with no linked product.
	 */
	private static function resolve_tax_rate_percent( array $item ) {
		if ( isset( $item['tax_rate'] ) && null !== $item['tax_rate'] ) {
			return (float) $item['tax_rate'];
		}

		// Gate on the store's own tax switch first, before even considering
		// the fallback rate — a free-text line on a store with WooCommerce
		// taxes disabled entirely must land at 0%, exactly like a
		// product-linked line does, not silently inherit the settings
		// fallback rate regardless of that switch.
		if ( ! wc_tax_enabled() ) {
			return 0.0;
		}

		if ( ! empty( $item['product_id'] ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $item['product_id'] );
			if ( $product ) {
				$rate = self::product_tax_rate_percent( $product );
				if ( null !== $rate ) {
					return $rate;
				}
			}
		}

		return WAQ_Settings::get_fallback_tax_rate();
	}

	/**
	 * Sums the store's base-location tax rates for a product's tax class.
	 * Quotes are drafted from the seller's side, not a specific customer's
	 * checkout address, so the store's base rate is the correct default —
	 * same simplification most quote-generation tools make.
	 */
	private static function product_tax_rate_percent( $product ) {
		if ( 'taxable' !== $product->get_tax_status() ) {
			return 0.0;
		}

		$rates = WC_Tax::get_base_tax_rates( $product->get_tax_class() );
		if ( empty( $rates ) ) {
			return null;
		}

		$total = 0.0;
		foreach ( $rates as $rate ) {
			$total += (float) $rate['rate'];
		}
		return $total;
	}
}
