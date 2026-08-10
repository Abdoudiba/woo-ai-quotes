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
class WAQ_AI_Drafter {

	/**
	 * @return array{items: array, error: string|null}
	 */
	public static function draft_from_request( $request_text ) {
		$provider = WAQ_Settings::get_provider();
		$api_key  = WAQ_Settings::get_api_key( $provider );

		if ( '' === $api_key ) {
			return array(
				'items' => array(),
				'error' => __( 'No AI API key configured — set one under WooCommerce → Settings → AI Quotes.', 'woo-ai-quotes' ),
			);
		}

		$raw = 'anthropic' === $provider
			? self::call_anthropic( $api_key, WAQ_Settings::get_model( $provider ), $request_text )
			: self::call_openai( $api_key, WAQ_Settings::get_model( $provider ), $request_text );

		if ( is_wp_error( $raw ) ) {
			return array( 'items' => array(), 'error' => $raw->get_error_message() );
		}

		$items = self::parse_items_json( $raw );
		if ( null === $items ) {
			return array(
				'items' => array(),
				'error' => __( 'The AI response could not be parsed as a line-item list. Try rephrasing the request.', 'woo-ai-quotes' ),
			);
		}

		return array( 'items' => self::match_against_catalog( $items ), 'error' => null );
	}

	private static function system_prompt() {
		return 'You turn a plain-language sales request into a structured list of quote line items. '
			. 'Output ONLY a JSON array, no prose, no markdown fences. Each element: '
			. '{"description": string, "qty": number}. '
			. "One element per distinct item requested. Infer a reasonable quantity if the customer didn't state one (default 1). "
			. 'Never include prices, taxes, or totals — those are computed separately from real catalog data.';
	}

	private static function call_openai( $api_key, $model, $request_text ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => self::system_prompt(),
							),
							array(
								'role'    => 'user',
								'content' => $request_text,
							),
						),
						'temperature' => 0.2,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 300 ) {
			$message = $body['error']['message'] ?? __( 'OpenAI request failed.', 'woo-ai-quotes' );
			return new WP_Error( 'waq_openai_error', $message );
		}

		return $body['choices'][0]['message']['content'] ?? '';
	}

	private static function call_anthropic( $api_key, $model, $request_text ) {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => 1024,
						'system'     => self::system_prompt(),
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $request_text,
							),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 300 ) {
			$message = $body['error']['message'] ?? __( 'Anthropic request failed.', 'woo-ai-quotes' );
			return new WP_Error( 'waq_anthropic_error', $message );
		}

		return $body['content'][0]['text'] ?? '';
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

	private static function find_best_matching_product( $search_term ) {
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
