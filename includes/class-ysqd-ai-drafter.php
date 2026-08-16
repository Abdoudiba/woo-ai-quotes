<?php
defined( 'ABSPATH' ) || exit;

/**
 * Turns a rep's plain-language request into draft line items.
 *
 * Split into two deliberately narrow steps, same "AI drafts loosely, code
 * resolves precisely" discipline as the rest of the plugin:
 *   1. AI call: request text -> {description, qty} pairs only. No prices,
 *      no product IDs — the model never sees the catalog and can't invent
 *      either.
 *   2. PHP: each description is matched against the real product catalog
 *      via a normal WooCommerce search; a match snapshots the product's
 *      real id/name/price, anything unmatched stays a free-text line with
 *      no price for the rep to fill in.
 * The rep reviews and edits every row before a quote is finalized — this
 * only produces a starting point, never a final document.
 */
class YSQD_AI_Drafter {

	/**
	 * @return array{items: array, error: string|null}
	 */
	public static function draft_from_request( $request_text ) {
		if ( ! self::is_available() ) {
			return array(
				'items' => array(),
				'error' => __( 'No AI provider connected — connect one under Settings → Connectors before drafting a quote.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			);
		}

		$raw = wp_ai_client_prompt( $request_text )
			->using_system_instruction( self::system_prompt() )
			->generate_text();

		if ( is_wp_error( $raw ) ) {
			return array( 'items' => array(), 'error' => $raw->get_error_message() );
		}

		$items = self::parse_items_json( $raw );
		if ( null === $items ) {
			return array(
				'items' => array(),
				'error' => __( 'The AI response could not be parsed as a line-item list. Try rephrasing the request.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			);
		}

		return array( 'items' => self::match_against_catalog( $items ), 'error' => null );
	}

	/**
	 * Whether the site owner has connected an AI provider capable of text
	 * generation under Settings → Connectors. Deterministic and free to call
	 * (no API request) per the WP AI Client docs.
	 */
	public static function is_available() {
		return wp_ai_client_prompt( '' )->is_supported_for_text_generation();
	}

	private static function system_prompt() {
		return 'You turn a plain-language sales request into a structured list of quote line items. '
			. 'Output ONLY a JSON array, no prose, no markdown fences. Each element: '
			. '{"description": string, "qty": number}. '
			. "One element per distinct item requested. Infer a reasonable quantity if the customer didn't state one (default 1). "
			. 'Never include prices, taxes, or totals — those are computed separately from real catalog data.';
	}

	private static function parse_items_json( $raw ) {
		$raw = trim( (string) $raw );
		$raw = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $raw );

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$items = array();
		foreach ( $decoded as $entry ) {
			if ( ! isset( $entry['description'] ) || '' === trim( (string) $entry['description'] ) ) {
				continue;
			}
			$items[] = array(
				'description' => sanitize_text_field( $entry['description'] ),
				'qty'         => isset( $entry['qty'] ) ? max( 0.01, (float) $entry['qty'] ) : 1.0,
			);
		}
		return $items;
	}

	/**
	 * Resolves each AI-drafted description against the real product
	 * catalog. A match snapshots the product's real id/name/price; anything
	 * unmatched stays a free-text line with no price for the rep to fill in
	 * or leave as a custom item — never a guessed price.
	 */
	private static function match_against_catalog( array $items ) {
		$resolved = array();

		foreach ( $items as $item ) {
			$product = self::find_best_matching_product( $item['description'] );

			if ( $product ) {
				$resolved[] = array(
					'product_id'  => $product->get_id(),
					'description' => $product->get_name(),
					'qty'         => $item['qty'],
					'unit_price'  => (float) $product->get_price(),
					'tax_rate'    => null,
				);
			} else {
				$resolved[] = array(
					'product_id'  => 0,
					'description' => $item['description'],
					'qty'         => $item['qty'],
					'unit_price'  => 0.0,
					'tax_rate'    => null,
				);
			}
		}

		return $resolved;
	}

	/**
	 * WordPress's default search requires every word in the query to match
	 * somewhere in the post (AND across terms) — it doesn't rank partial
	 * matches. A trailing generic/category word in a different language
	 * than the catalog (e.g. an AI-drafted "Keyboard" against a French
	 * "Clavier" listing) can fail the whole query even when the brand and
	 * model earlier in the phrase would have matched cleanly on their own.
	 * So: try the full description first, then progressively drop trailing
	 * words and retry, stopping at a single word.
	 */
	private static function find_best_matching_product( $search_term ) {
		$words = preg_split( '/\s+/', trim( (string) $search_term ) );
		$words = array_filter( $words, 'strlen' );

		while ( ! empty( $words ) ) {
			$product = self::search_product( implode( ' ', $words ) );
			if ( $product ) {
				return $product;
			}
			array_pop( $words );
		}

		return null;
	}

	private static function search_product( $search_term ) {
		if ( '' === trim( $search_term ) ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				's'              => $search_term,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		$product = wc_get_product( $query->posts[0] );
		return $product ?: null;
	}
}
