<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin screens: quote list, new-quote intake (plain-language request ->
 * AI draft), and the line-item editor. Custom-built rather than the default
 * post-edit screen because none of this fits a normal post editor — same
 * call woo-geo-catalog made for its settings UI, just a bigger surface here.
 */
class YSQD_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		// admin-post.php dispatches on admin_post_{action} (or admin_post_nopriv_*
		// for guests) — not admin_action_{action}, which is a different hook
		// fired by admin.php for list-table row actions. Getting this prefix
		// wrong means nothing is ever hooked to what admin-post.php actually
		// fires, and core's own safety net kills the request with a blank
		// wp_die( '', 400 ) rather than silently doing nothing.
		add_action( 'admin_post_ysqd_create_draft', array( __CLASS__, 'handle_create_draft' ) );
		add_action( 'admin_post_ysqd_save_quote', array( __CLASS__, 'handle_save_quote' ) );
		add_action( 'admin_post_ysqd_finalize_quote', array( __CLASS__, 'handle_finalize_quote' ) );
		add_action( 'admin_post_ysqd_download_pdf', array( __CLASS__, 'handle_download_pdf' ) );
		add_action( 'admin_post_ysqd_delete_quote', array( __CLASS__, 'handle_delete_quote' ) );
		add_action( 'wp_ajax_ysqd_get_product', array( __CLASS__, 'ajax_get_product' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'AI Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			__( 'AI Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			self::CAP,
			'ysqd-quotes',
			array( __CLASS__, 'render_list_page' ),
			'dashicons-media-document',
			56
		);
		add_submenu_page( 'ysqd-quotes', __( 'All Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' ), __( 'All Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' ), self::CAP, 'ysqd-quotes', array( __CLASS__, 'render_list_page' ) );
		add_submenu_page( 'ysqd-quotes', __( 'New Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ), __( 'New Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ), self::CAP, 'ysqd-new-quote', array( __CLASS__, 'render_new_page' ) );
		// Registered but hidden from the menu — reached via row actions on the list page.
		add_submenu_page( null, __( 'Edit Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ), __( 'Edit Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ), self::CAP, 'ysqd-edit-quote', array( __CLASS__, 'render_edit_page' ) );
	}

	public static function enqueue_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: only decides which assets to enqueue, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'ysqd-' ) ) {
			return;
		}

		wp_enqueue_style( 'ysqd-admin', YSQD_PLUGIN_URL . 'assets/admin.css', array(), YSQD_VERSION );

		// Only the edit screen has the line-item table and product search —
		// the list and new-quote intake pages don't need this JS.
		if ( 'ysqd-edit-quote' !== $page ) {
			return;
		}

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'ysqd-editor', YSQD_PLUGIN_URL . 'assets/quote-editor.js', array( 'jquery', 'wc-enhanced-select' ), YSQD_VERSION, true );
		wp_localize_script(
			'ysqd-editor',
			'YSQD_Editor',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ysqd_get_product' ),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'i18n'           => array(
					'remove' => __( 'Remove', 'yuupee-smart-quote-drafting-for-woocommerce' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * List page
	 * ------------------------------------------------------------------- */

	public static function render_list_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		$quotes = get_posts(
			array(
				'post_type'      => YSQD_Quote_Post_Type::POST_TYPE,
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		self::render_notice();
		?>
		<div class="wrap ysqd-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quotes', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ysqd-new-quote' ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( ! YSQD_AI_Drafter::is_available() ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: %s: settings link */
						esc_html__( 'No AI provider connected yet — connect one under %s before drafting a quote.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
						'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings → Connectors', 'yuupee-smart-quote-drafting-for-woocommerce' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Quote #', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Total', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Date', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $quotes ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No quotes yet.', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $quotes as $quote ) : ?>
						<?php
						$customer = YSQD_Quote_Post_Type::get_customer( $quote->ID );
						$totals   = YSQD_Quote_Post_Type::get_totals( $quote->ID );
						$number   = YSQD_Quote_Post_Type::get_quote_number( $quote->ID );
						$edit_url = admin_url( 'admin.php?page=ysqd-edit-quote&quote_id=' . $quote->ID );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $number ?: __( '(draft)', 'yuupee-smart-quote-drafting-for-woocommerce' ) ); ?></a></td>
							<td><?php echo esc_html( $customer['name'] ?: '—' ); ?></td>
							<td>
								<?php if ( 'publish' === $quote->post_status ) : ?>
									<span class="ysqd-status ysqd-status-final"><?php esc_html_e( 'Finalized', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></span>
								<?php else : ?>
									<span class="ysqd-status ysqd-status-draft"><?php esc_html_e( 'Draft', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></td>
							<td><?php echo esc_html( get_the_date( '', $quote ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></a>
								<?php if ( 'publish' === $quote->post_status ) : ?>
									| <a href="<?php echo esc_url( self::download_url( $quote->ID ) ); ?>"><?php esc_html_e( 'Download PDF', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></a>
								<?php endif; ?>
								| <a href="<?php echo esc_url( self::delete_url( $quote->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this quote?', 'yuupee-smart-quote-drafting-for-woocommerce' ) ); ?>');"><?php esc_html_e( 'Delete', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * New-quote intake page
	 * ------------------------------------------------------------------- */

	public static function render_new_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}
		?>
		<div class="wrap ysqd-wrap">
			<h1><?php esc_html_e( 'New Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Describe what the customer wants in plain language. AI drafts a starting line-item list from your real catalog — you review and adjust everything before it becomes a quote.', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ysqd_create_draft">
				<?php wp_nonce_field( 'ysqd_create_draft' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="ysqd_customer_name"><?php esc_html_e( 'Customer name', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></label></th>
						<td><input type="text" id="ysqd_customer_name" name="customer_name" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="ysqd_customer_email"><?php esc_html_e( 'Customer email', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></label></th>
						<td><input type="email" id="ysqd_customer_email" name="customer_email" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="ysqd_request_text"><?php esc_html_e( 'Request', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></label></th>
						<td>
							<textarea id="ysqd_request_text" name="request_text" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Client wants 5 HP laptops and a UPS, budget-conscious, deliver to Dakar', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Leave blank to start an empty quote and add line items manually.', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Create Quote', 'yuupee-smart-quote-drafting-for-woocommerce' ) ); ?>
				<?php if ( ! YSQD_AI_Drafter::is_available() ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: settings link */
							esc_html__( 'No AI provider connected — connect one under %s to enable AI drafting, or leave the request blank to build the quote manually.', 'yuupee-smart-quote-drafting-for-woocommerce' ),
							'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings → Connectors', 'yuupee-smart-quote-drafting-for-woocommerce' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public static function handle_create_draft() {
		check_admin_referer( 'ysqd_create_draft' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		$customer_name  = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$customer_email = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$request_text   = sanitize_textarea_field( wp_unslash( $_POST['request_text'] ?? '' ) );

		$quote_id = wp_insert_post(
			array(
				'post_type'   => YSQD_Quote_Post_Type::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $customer_name ?: __( 'Untitled quote', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			)
		);

		if ( is_wp_error( $quote_id ) || ! $quote_id ) {
			wp_die( esc_html__( 'Could not create the quote. Please try again.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		YSQD_Quote_Post_Type::save_customer( $quote_id, $customer_name, $customer_email );
		update_post_meta( $quote_id, YSQD_Quote_Post_Type::META_REQUEST_TEXT, $request_text );

		$notice = 'drafted';
		if ( '' !== $request_text ) {
			$result = YSQD_AI_Drafter::draft_from_request( $request_text );
			if ( $result['error'] ) {
				YSQD_Quote_Post_Type::save_line_items( $quote_id, array() );
				set_transient( 'ysqd_notice_' . get_current_user_id(), $result['error'], 60 );
				$notice = 'ai_error';
			} else {
				YSQD_Quote_Post_Type::save_line_items( $quote_id, $result['items'] );
				YSQD_Quote_Post_Type::save_totals( $quote_id, YSQD_Calculator::calculate( $result['items'] ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ysqd-edit-quote&quote_id=' . $quote_id . '&ysqd_notice=' . $notice ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Edit page
	 * ------------------------------------------------------------------- */

	public static function render_edit_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: only selects which quote to display, gated by the capability check above.
		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		$quote    = $quote_id ? get_post( $quote_id ) : null;

		if ( ! $quote || YSQD_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		self::render_notice();

		$customer   = YSQD_Quote_Post_Type::get_customer( $quote_id );
		$line_items = YSQD_Quote_Post_Type::get_line_items( $quote_id );
		$totals     = YSQD_Quote_Post_Type::get_totals( $quote_id );
		$is_final   = 'publish' === $quote->post_status;
		?>
		<div class="wrap ysqd-wrap">
			<h1>
				<?php echo esc_html( YSQD_Quote_Post_Type::get_quote_number( $quote_id ) ?: __( 'Draft quote', 'yuupee-smart-quote-drafting-for-woocommerce' ) ); ?>
				<?php if ( $is_final ) : ?><span class="ysqd-status ysqd-status-final"><?php esc_html_e( 'Finalized', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></span><?php endif; ?>
			</h1>

			<?php if ( $is_final ) : ?>
				<p class="description"><?php esc_html_e( 'This quote is finalized. Numbers are locked to when it was sent, even if catalog prices change later. You can still edit and re-finalize if needed.', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ysqd-quote-form">
				<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>">

				<table class="form-table">
					<tr>
						<th><label for="ysqd_customer_name"><?php esc_html_e( 'Customer name', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></label></th>
						<td><input type="text" id="ysqd_customer_name" name="customer_name" class="regular-text" value="<?php echo esc_attr( $customer['name'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="ysqd_customer_email"><?php esc_html_e( 'Customer email', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></label></th>
						<td><input type="email" id="ysqd_customer_email" name="customer_email" class="regular-text" value="<?php echo esc_attr( $customer['email'] ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Line items', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></h2>
				<table class="wp-list-table widefat fixed striped" id="ysqd-items-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Description', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'Qty', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Unit price', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Tax % override', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
							<th style="width:60px;"></th>
						</tr>
					</thead>
					<tbody id="ysqd-items-body">
						<?php foreach ( $line_items as $i => $item ) : ?>
							<?php self::render_item_row( $i, $item ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="ysqd-add-custom-row"><?php esc_html_e( '+ Add custom line', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></button>
					<select id="ysqd-add-product" style="min-width:320px;" data-placeholder="<?php esc_attr_e( '+ Add product from catalog…', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?>" class="wc-product-search" data-action="woocommerce_json_search_products_and_variations"></select>
				</p>

				<div class="ysqd-totals-box">
					<p><?php esc_html_e( 'Subtotal:', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?> <span id="ysqd-preview-subtotal"><?php echo wp_kses_post( wc_price( $totals['subtotal'] ) ); ?></span></p>
					<p><?php esc_html_e( 'Tax:', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?> <span id="ysqd-preview-tax"><?php echo wp_kses_post( wc_price( $totals['tax'] ) ); ?></span></p>
					<p><strong><?php esc_html_e( 'Total:', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?> <span id="ysqd-preview-total"><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></span></strong></p>
					<p class="description"><?php esc_html_e( 'Preview only — exact totals are recalculated from real catalog/tax data when you save.', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></p>
				</div>

				<p class="submit">
					<?php wp_nonce_field( 'ysqd_save_quote' ); ?>
					<button type="submit" name="action" value="ysqd_save_quote" class="button"><?php esc_html_e( 'Save Draft', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></button>
					<button type="submit" name="action" value="ysqd_finalize_quote" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Finalize this quote? Its quote number and totals will be locked in.', 'yuupee-smart-quote-drafting-for-woocommerce' ) ); ?>');"><?php esc_html_e( 'Finalize & Download PDF', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></button>
					<?php if ( $is_final ) : ?>
						<a href="<?php echo esc_url( self::download_url( $quote_id ) ); ?>" class="button"><?php esc_html_e( 'Download PDF', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<script type="text/template" id="ysqd-row-template">
			<?php self::render_item_row( '__INDEX__', array( 'product_id' => 0, 'description' => '', 'qty' => 1, 'unit_price' => 0, 'tax_rate' => null ), true ); ?>
		</script>
		<?php
	}

	private static function render_item_row( $index, array $item, $is_template = false ) {
		$name_prefix = 'items[' . $index . ']';
		?>
		<tr class="ysqd-item-row">
			<td>
				<input type="hidden" class="ysqd-product-id" name="<?php echo esc_attr( $name_prefix ); ?>[product_id]" value="<?php echo esc_attr( $item['product_id'] ?? 0 ); ?>">
				<input type="text" class="regular-text ysqd-description" name="<?php echo esc_attr( $name_prefix ); ?>[description]" value="<?php echo $is_template ? '' : esc_attr( $item['description'] ?? '' ); ?>">
			</td>
			<td><input type="number" step="0.01" min="0" class="small-text ysqd-qty" name="<?php echo esc_attr( $name_prefix ); ?>[qty]" value="<?php echo esc_attr( $item['qty'] ?? 1 ); ?>"></td>
			<td><input type="number" step="0.01" min="0" class="ysqd-unit-price" name="<?php echo esc_attr( $name_prefix ); ?>[unit_price]" value="<?php echo esc_attr( $item['unit_price'] ?? 0 ); ?>"></td>
			<td><input type="number" step="0.01" min="0" class="ysqd-tax-rate" name="<?php echo esc_attr( $name_prefix ); ?>[tax_rate]" value="<?php echo esc_attr( $item['tax_rate'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'auto', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?>"></td>
			<td><button type="button" class="button-link ysqd-remove-row"><?php esc_html_e( 'Remove', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></button></td>
		</tr>
		<?php
	}

	public static function handle_save_quote() {
		self::save_from_request( false );
	}

	public static function handle_finalize_quote() {
		self::save_from_request( true );
	}

	private static function save_from_request( $finalize ) {
		check_admin_referer( 'ysqd_save_quote' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
		$quote    = $quote_id ? get_post( $quote_id ) : null;
		if ( ! $quote || YSQD_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}

		$customer_name  = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$customer_email = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		YSQD_Quote_Post_Type::save_customer( $quote_id, $customer_name, $customer_email );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized/type-cast immediately below in YSQD_Quote_Post_Type::save_line_items() -> sanitize_line_item(), before anything is stored.
		$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items     = array();
		foreach ( $raw_items as $raw_item ) {
			if ( '' === trim( (string) ( $raw_item['description'] ?? '' ) ) ) {
				continue;
			}
			$items[] = $raw_item;
		}
		YSQD_Quote_Post_Type::save_line_items( $quote_id, $items );

		$saved_items = YSQD_Quote_Post_Type::get_line_items( $quote_id );
		YSQD_Quote_Post_Type::save_totals( $quote_id, YSQD_Calculator::calculate( $saved_items ) );

		wp_update_post(
			array(
				'ID'         => $quote_id,
				'post_title' => $customer_name ?: __( 'Untitled quote', 'yuupee-smart-quote-drafting-for-woocommerce' ),
			)
		);

		if ( $finalize ) {
			YSQD_Quote_Post_Type::assign_quote_number( $quote_id );
			wp_update_post(
				array(
					'ID'          => $quote_id,
					'post_status' => 'publish',
				)
			);
			wp_safe_redirect( self::download_url( $quote_id ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ysqd-edit-quote&quote_id=' . $quote_id . '&ysqd_notice=saved' ) );
		exit;
	}

	public static function handle_download_pdf() {
		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		check_admin_referer( 'ysqd_download_' . $quote_id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}
		$quote = get_post( $quote_id );
		if ( ! $quote || YSQD_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}
		YSQD_PDF::stream( $quote_id );
	}

	public static function handle_delete_quote() {
		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		check_admin_referer( 'ysqd_delete_' . $quote_id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'yuupee-smart-quote-drafting-for-woocommerce' ) );
		}
		wp_trash_post( $quote_id );
		wp_safe_redirect( admin_url( 'admin.php?page=ysqd-quotes&ysqd_notice=deleted' ) );
		exit;
	}

	public static function ajax_get_product() {
		check_ajax_referer( 'ysqd_get_product', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error();
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			wp_send_json_error();
		}
		wp_send_json_success(
			array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => (float) $product->get_price(),
			)
		);
	}

	/**
	 * Builds a raw (non-HTML-escaped) nonce'd admin-post.php URL. Deliberately
	 * not wp_nonce_url() — that function always HTML-escapes its output
	 * (`&` -> `&amp;`), which is correct for echoing into an href but breaks
	 * a raw Location redirect (the literal "amp;" ends up in quote_id/
	 * _wpnonce, corrupting both). Callers echoing this into HTML should wrap
	 * it in esc_url() themselves at the point of output.
	 */
	private static function nonce_action_url( $action, $quote_id, $nonce_action ) {
		$url = add_query_arg(
			array(
				'action'   => $action,
				'quote_id' => $quote_id,
			),
			admin_url( 'admin-post.php' )
		);
		return add_query_arg( '_wpnonce', wp_create_nonce( $nonce_action ), $url );
	}

	private static function download_url( $quote_id ) {
		return self::nonce_action_url( 'ysqd_download_pdf', $quote_id, 'ysqd_download_' . $quote_id );
	}

	private static function delete_url( $quote_id ) {
		return self::nonce_action_url( 'ysqd_delete_quote', $quote_id, 'ysqd_delete_' . $quote_id );
	}

	private static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: only selects which admin notice text to display, sanitized via sanitize_key().
		$notice = isset( $_GET['ysqd_notice'] ) ? sanitize_key( $_GET['ysqd_notice'] ) : '';
		if ( 'ai_error' === $notice ) {
			$message = get_transient( 'ysqd_notice_' . get_current_user_id() );
			delete_transient( 'ysqd_notice_' . get_current_user_id() );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ?: __( 'The AI draft failed.', 'yuupee-smart-quote-drafting-for-woocommerce' ) ) );
		} elseif ( 'saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote saved.', 'yuupee-smart-quote-drafting-for-woocommerce' ) . '</p></div>';
		} elseif ( 'drafted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Draft created — review the line items below before finalizing.', 'yuupee-smart-quote-drafting-for-woocommerce' ) . '</p></div>';
		} elseif ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote deleted.', 'yuupee-smart-quote-drafting-for-woocommerce' ) . '</p></div>';
		}
	}
}
