<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint wrapping the same draft pipeline the admin "New Quote"
 * screen uses (AI draft -> catalog match -> calculate), so any external
 * caller — the Yuupee WhatsApp bot, or any other automation a buyer wants
 * to wire up — can create a draft quote without a form submission.
 *
 * Deliberately creates a DRAFT only, never finalizes: same "AI drafts,
 * human reviews before anything final" rule as the admin UI itself and
 * the rest of this project's tooling (draft_social_post/publish_social_post
 * in the Yuupee OpenClaw plugin, for one).
 *
 * Auth: WordPress core Application Passwords (native since WP 5.6) — no
 * extra dependency, works over standard REST Basic Auth, per-user
 * revocable. Not WooCommerce's consumer key/secret scheme, which only
 * covers the wc/* namespace, not custom routes like this one.
 */
class WAQ_REST {

	const NAMESPACE = 'waq/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/quotes',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_quote' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'customer_name'  => array(
						'type'     => 'string',
						'required' => false,
					),
					'customer_email' => array(
						'type'     => 'string',
						'required' => false,
					),
					'request_text'   => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public static function check_permission() {
		return current_user_can( WAQ_Admin::CAP );
	}

	public static function create_quote( WP_REST_Request $request ) {
		$customer_name  = sanitize_text_field( (string) $request->get_param( 'customer_name' ) );
		$customer_email = sanitize_email( (string) $request->get_param( 'customer_email' ) );
		$request_text   = sanitize_textarea_field( (string) $request->get_param( 'request_text' ) );

		if ( '' === trim( $request_text ) ) {
			return new WP_Error( 'waq_missing_request_text', __( 'request_text is required.', 'woo-ai-quotes' ), array( 'status' => 400 ) );
		}

		if ( ! WAQ_Settings::is_configured() ) {
			return new WP_Error( 'waq_ai_not_configured', __( 'No AI provider configured — set one under WooCommerce → Settings → AI Quotes.', 'woo-ai-quotes' ), array( 'status' => 400 ) );
		}

		$quote_id = wp_insert_post(
			array(
				'post_type'   => WAQ_Quote_Post_Type::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $customer_name ?: __( 'Untitled quote', 'woo-ai-quotes' ),
			),
			true
		);
		if ( is_wp_error( $quote_id ) ) {
			return new WP_Error( 'waq_create_failed', __( 'Could not create the quote.', 'woo-ai-quotes' ), array( 'status' => 500 ) );
		}

		WAQ_Quote_Post_Type::save_customer( $quote_id, $customer_name, $customer_email );
		update_post_meta( $quote_id, WAQ_Quote_Post_Type::META_REQUEST_TEXT, $request_text );

		$result = WAQ_AI_Drafter::draft_from_request( $request_text );
		if ( $result['error'] ) {
			// The quote row stays (empty) rather than being deleted — same
			// behavior as the admin intake form on an AI failure, so nothing
			// silently vanishes and the caller still has a quote_id to point
			// a rep at if they want to build it manually instead.
			return new WP_Error( 'waq_ai_draft_failed', $result['error'], array( 'status' => 502 ) );
		}

		WAQ_Quote_Post_Type::save_line_items( $quote_id, $result['items'] );
		$totals = WAQ_Calculator::calculate( $result['items'] );
		WAQ_Quote_Post_Type::save_totals( $quote_id, $totals );

		return array(
			'quote_id'   => $quote_id,
			'status'     => 'draft',
			'edit_url'   => admin_url( 'admin.php?page=waq-edit-quote&quote_id=' . $quote_id ),
			'line_items' => WAQ_Quote_Post_Type::get_line_items( $quote_id ),
			'totals'     => $totals,
		);
	}
}
