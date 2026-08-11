<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin screens: quote list, new-quote intake (plain-language request ->
 * AI draft), and the line-item editor. Custom-built rather than the default
 * post-edit screen because none of this fits a normal post editor — same
 * call woo-geo-catalog made for its settings UI, just a bigger surface here.
 */
class WAQ_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_action_waq_create_draft', array( __CLASS__, 'handle_create_draft' ) );
		add_action( 'admin_action_waq_save_quote', array( __CLASS__, 'handle_save_quote' ) );
		add_action( 'admin_action_waq_finalize_quote', array( __CLASS__, 'handle_finalize_quote' ) );
		add_action( 'admin_action_waq_download_pdf', array( __CLASS__, 'handle_download_pdf' ) );
		add_action( 'admin_action_waq_delete_quote', array( __CLASS__, 'handle_delete_quote' ) );
		add_action( 'wp_ajax_waq_get_product', array( __CLASS__, 'ajax_get_product' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'AI Quotes', 'woo-ai-quotes' ),
			__( 'AI Quotes', 'woo-ai-quotes' ),
			self::CAP,
			'waq-quotes',
			array( __CLASS__, 'render_list_page' ),
			'dashicons-media-document',
			56
		);
		add_submenu_page( 'waq-quotes', __( 'All Quotes', 'woo-ai-quotes' ), __( 'All Quotes', 'woo-ai-quotes' ), self::CAP, 'waq-quotes', array( __CLASS__, 'render_list_page' ) );
		add_submenu_page( 'waq-quotes', __( 'New Quote', 'woo-ai-quotes' ), __( 'New Quote', 'woo-ai-quotes' ), self::CAP, 'waq-new-quote', array( __CLASS__, 'render_new_page' ) );
		// Registered but hidden from the menu — reached via row actions on the list page.
		add_submenu_page( null, __( 'Edit Quote', 'woo-ai-quotes' ), __( 'Edit Quote', 'woo-ai-quotes' ), self::CAP, 'waq-edit-quote', array( __CLASS__, 'render_edit_page' ) );
	}

	public static function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'waq-' ) ) {
			return;
		}

		wp_enqueue_style( 'waq-admin', WAQ_PLUGIN_URL . 'assets/admin.css', array(), WAQ_VERSION );

		// Only the edit screen has the line-item table and product search —
		// the list and new-quote intake pages don't need this JS.
		if ( 'waq-edit-quote' !== $page ) {
			return;
		}

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'waq-editor', WAQ_PLUGIN_URL . 'assets/quote-editor.js', array( 'jquery', 'wc-enhanced-select' ), WAQ_VERSION, true );
		wp_localize_script(
			'waq-editor',
			'WAQ_Editor',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'waq_get_product' ),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'i18n'           => array(
					'remove' => __( 'Remove', 'woo-ai-quotes' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * List page
	 * ------------------------------------------------------------------- */

	public static function render_list_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-ai-quotes' ) );
		}

		$quotes = get_posts(
			array(
				'post_type'      => WAQ_Quote_Post_Type::POST_TYPE,
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		self::render_notice();
		?>
		<div class="wrap waq-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quotes', 'woo-ai-quotes' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=waq-new-quote' ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Quote', 'woo-ai-quotes' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( ! WAQ_Settings::is_configured() ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: %s: settings link */
						esc_html__( 'No AI provider configured yet — set an API key under %s before drafting a quote.', 'woo-ai-quotes' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=waq' ) ) . '">' . esc_html__( 'WooCommerce → Settings → AI Quotes', 'woo-ai-quotes' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Quote #', 'woo-ai-quotes' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'woo-ai-quotes' ); ?></th>
						<th><?php esc_html_e( 'Status', 'woo-ai-quotes' ); ?></th>
						<th><?php esc_html_e( 'Total', 'woo-ai-quotes' ); ?></th>
						<th><?php esc_html_e( 'Date', 'woo-ai-quotes' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $quotes ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No quotes yet.', 'woo-ai-quotes' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $quotes as $quote ) : ?>
						<?php
						$customer = WAQ_Quote_Post_Type::get_customer( $quote->ID );
						$totals   = WAQ_Quote_Post_Type::get_totals( $quote->ID );
						$number   = WAQ_Quote_Post_Type::get_quote_number( $quote->ID );
						$edit_url = admin_url( 'admin.php?page=waq-edit-quote&quote_id=' . $quote->ID );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $number ?: __( '(draft)', 'woo-ai-quotes' ) ); ?></a></td>
							<td><?php echo esc_html( $customer['name'] ?: '—' ); ?></td>
							<td>
								<?php if ( 'publish' === $quote->post_status ) : ?>
									<span class="waq-status waq-status-final"><?php esc_html_e( 'Finalized', 'woo-ai-quotes' ); ?></span>
								<?php else : ?>
									<span class="waq-status waq-status-draft"><?php esc_html_e( 'Draft', 'woo-ai-quotes' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></td>
							<td><?php echo esc_html( get_the_date( '', $quote ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'woo-ai-quotes' ); ?></a>
								<?php if ( 'publish' === $quote->post_status ) : ?>
									| <a href="<?php echo esc_url( self::download_url( $quote->ID ) ); ?>"><?php esc_html_e( 'Download PDF', 'woo-ai-quotes' ); ?></a>
								<?php endif; ?>
								| <a href="<?php echo esc_url( self::delete_url( $quote->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this quote?', 'woo-ai-quotes' ) ); ?>');"><?php esc_html_e( 'Delete', 'woo-ai-quotes' ); ?></a>
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-ai-quotes' ) );
		}
		?>
		<div class="wrap waq-wrap">
			<h1><?php esc_html_e( 'New Quote', 'woo-ai-quotes' ); ?></h1>
			<p><?php esc_html_e( 'Describe what the customer wants in plain language. AI drafts a starting line-item list from your real catalog — you review and adjust everything before it becomes a quote.', 'woo-ai-quotes' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="waq_create_draft">
				<?php wp_nonce_field( 'waq_create_draft' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="waq_customer_name"><?php esc_html_e( 'Customer name', 'woo-ai-quotes' ); ?></label></th>
						<td><input type="text" id="waq_customer_name" name="customer_name" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="waq_customer_email"><?php esc_html_e( 'Customer email', 'woo-ai-quotes' ); ?></label></th>
						<td><input type="email" id="waq_customer_email" name="customer_email" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="waq_request_text"><?php esc_html_e( 'Request', 'woo-ai-quotes' ); ?></label></th>
						<td>
							<textarea id="waq_request_text" name="request_text" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Client wants 5 HP laptops and a UPS, budget-conscious, deliver to Dakar', 'woo-ai-quotes' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Leave blank to start an empty quote and add line items manually.', 'woo-ai-quotes' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Create Quote', 'woo-ai-quotes' ) ); ?>
				<?php if ( ! WAQ_Settings::is_configured() ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: settings link */
							esc_html__( 'No AI provider configured — set one under %s to enable AI drafting, or leave the request blank to build the quote manually.', 'woo-ai-quotes' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=waq' ) ) . '">' . esc_html__( 'WooCommerce → Settings → AI Quotes', 'woo-ai-quotes' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public static function handle_create_draft() {
		check_admin_referer( 'waq_create_draft' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-ai-quotes' ) );
		}

		$customer_name  = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$customer_email = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$request_text   = sanitize_textarea_field( wp_unslash( $_POST['request_text'] ?? '' ) );

		$quote_id = wp_insert_post(
			array(
				'post_type'   => WAQ_Quote_Post_Type::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $customer_name ?: __( 'Untitled quote', 'woo-ai-quotes' ),
			)
		);

		if ( is_wp_error( $quote_id ) || ! $quote_id ) {
			wp_die( esc_html__( 'Could not create the quote. Please try again.', 'woo-ai-quotes' ) );
		}

		WAQ_Quote_Post_Type::save_customer( $quote_id, $customer_name, $customer_email );
		update_post_meta( $quote_id, WAQ_Quote_Post_Type::META_REQUEST_TEXT, $request_text );

		$notice = 'drafted';
		if ( '' !== $request_text ) {
			$result = WAQ_AI_Drafter::draft_from_request( $request_text );
			if ( $result['error'] ) {
				WAQ_Quote_Post_Type::save_line_items( $quote_id, array() );
				set_transient( 'waq_notice_' . get_current_user_id(), $result['error'], 60 );
				$notice = 'ai_error';
			} else {
				WAQ_Quote_Post_Type::save_line_items( $quote_id, $result['items'] );
				WAQ_Quote_Post_Type::save_totals( $quote_id, WAQ_Calculator::calculate( $result['items'] ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=waq-edit-quote&quote_id=' . $quote_id . '&waq_notice=' . $notice ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Edit page
	 * ------------------------------------------------------------------- */

	public static function render_edit_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-ai-quotes' ) );
		}

		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		$quote    = $quote_id ? get_post( $quote_id ) : null;

		if ( ! $quote || WAQ_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'woo-ai-quotes' ) );
		}

		self::render_notice();

		$customer   = WAQ_Quote_Post_Type::get_customer( $quote_id );
		$line_items = WAQ_Quote_Post_Type::get_line_items( $quote_id );
		$totals     = WAQ_Quote_Post_Type::get_totals( $quote_id );
		$is_final   = 'publish' === $quote->post_status;
		?>
		<div class="wrap waq-wrap">
			<h1>
				<?php echo esc_html( WAQ_Quote_Post_Type::get_quote_number( $quote_id ) ?: __( 'Draft quote', 'woo-ai-quotes' ) ); ?>
				<?php if ( $is_final ) : ?><span class="waq-status waq-status-final"><?php esc_html_e( 'Finalized', 'woo-ai-quotes' ); ?></span><?php endif; ?>
			</h1>

			<?php if ( $is_final ) : ?>
				<p class="description"><?php esc_html_e( 'This quote is finalized. Numbers are locked to when it was sent, even if catalog prices change later. You can still edit and re-finalize if needed.', 'woo-ai-quotes' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="waq-quote-form">
				<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>">

				<table class="form-table">
					<tr>
						<th><label for="waq_customer_name"><?php esc_html_e( 'Customer name', 'woo-ai-quotes' ); ?></label></th>
						<td><input type="text" id="waq_customer_name" name="customer_name" class="regular-text" value="<?php echo esc_attr( $customer['name'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="waq_customer_email"><?php esc_html_e( 'Customer email', 'woo-ai-quotes' ); ?></label></th>
						<td><input type="email" id="waq_customer_email" name="customer_email" class="regular-text" value="<?php echo esc_attr( $customer['email'] ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Line items', 'woo-ai-quotes' ); ?></h2>
				<table class="wp-list-table widefat fixed striped" id="waq-items-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Description', 'woo-ai-quotes' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'Qty', 'woo-ai-quotes' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Unit price', 'woo-ai-quotes' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Tax % override', 'woo-ai-quotes' ); ?></th>
							<th style="width:60px;"></th>
						</tr>
					</thead>
					<tbody id="waq-items-body">
						<?php foreach ( $line_items as $i => $item ) : ?>
							<?php self::render_item_row( $i, $item ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="waq-add-custom-row"><?php esc_html_e( '+ Add custom line', 'woo-ai-quotes' ); ?></button>
					<select id="waq-add-product" style="min-width:320px;" data-placeholder="<?php esc_attr_e( '+ Add product from catalog…', 'woo-ai-quotes' ); ?>" class="wc-product-search" data-action="woocommerce_json_search_products_and_variations"></select>
				</p>

				<div class="waq-totals-box">
					<p><?php esc_html_e( 'Subtotal:', 'woo-ai-quotes' ); ?> <span id="waq-preview-subtotal"><?php echo wp_kses_post( wc_price( $totals['subtotal'] ) ); ?></span></p>
					<p><?php esc_html_e( 'Tax:', 'woo-ai-quotes' ); ?> <span id="waq-preview-tax"><?php echo wp_kses_post( wc_price( $totals['tax'] ) ); ?></span></p>
					<p><strong><?php esc_html_e( 'Total:', 'woo-ai-quotes' ); ?> <span id="waq-preview-total"><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></span></strong></p>
					<p class="description"><?php esc_html_e( 'Preview only — exact totals are recalculated from real catalog/tax data when you save.', 'woo-ai-quotes' ); ?></p>
				</div>

				<p class="submit">
					<?php wp_nonce_field( 'waq_save_quote' ); ?>
					<button type="submit" name="action" value="waq_save_quote" class="button"><?php esc_html_e( 'Save Draft', 'woo-ai-quotes' ); ?></button>
					<button type="submit" name="action" value="waq_finalize_quote" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Finalize this quote? Its quote number and totals will be locked in.', 'woo-ai-quotes' ) ); ?>');"><?php esc_html_e( 'Finalize & Download PDF', 'woo-ai-quotes' ); ?></button>
					<?php if ( $is_final ) : ?>
						<a href="<?php echo esc_url( self::download_url( $quote_id ) ); ?>" class="button"><?php esc_html_e( 'Download PDF', 'woo-ai-quotes' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<script type="text/template" id="waq-row-template">
			<?php self::render_item_row( '__INDEX__', array( 'product_id' => 0, 'description' => '', 'qty' => 1, 'unit_price' => 0, 'tax_rate' => null ), true ); ?>
		</script>
		<?php
	}

	private static function render_item_row( $index, array $item, $is_template = false ) {
		$name_prefix = 'items[' . $index . ']';
		?>
		<tr class="waq-item-row">
			<td>
				<input type="hidden" class="waq-product-id" name="<?php echo esc_attr( $name_prefix ); ?>[product_id]" value="<?php echo esc_attr( $item['product_id'] ?? 0 ); ?>">
				<input type="text" class="regular-text waq-description" name="<?php echo esc_attr( $name_prefix ); ?>[description]" value="<?php echo $is_template ? '' : esc_attr( $item['description'] ?? '' ); ?>">
			</td>
			<td><input type="number" step="0.01" min="0" class="small-text waq-qty" name="<?php echo esc_attr( $name_prefix ); ?>[qty]" value="<?php echo esc_attr( $item['qty'] ?? 1 ); ?>"></td>
			<td><input type="number" step="0.01" min="0" class="waq-unit-price" name="<?php echo esc_attr( $name_prefix ); ?>[unit_price]" value="<?php echo esc_attr( $item['unit_price'] ?? 0 ); ?>"></td>
			<td><input type="number" step="0.01" min="0" class="waq-tax-rate" name="<?php echo esc_attr( $name_prefix ); ?>[tax_rate]" value="<?php echo esc_attr( $item['tax_rate'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'auto', 'woo-ai-quotes' ); ?>"></td>
			<td><button type="button" class="button-link waq-remove-row"><?php esc_html_e( 'Remove', 'woo-ai-quotes' ); ?></button></td>
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
		check_admin_referer( 'waq_save_quote' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-ai-quotes' ) );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
		$quote    = $quote_id ? get_post( $quote_id ) : null;
		if ( ! $quote || WAQ_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'woo-ai-quotes' ) );
		}

		$customer_name  = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$customer_email = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		WAQ_Quote_Post_Type::save_customer( $quote_id, $customer_name, $customer_email );

		$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items     = array();
		foreach ( $raw_items as $raw_item ) {
			if ( '' === trim( (string) ( $raw_item['description'] ?? '' ) ) ) {
				continue;
			}
			$items[] = $raw_item;
		}
		WAQ_Quote_Post_Type::save_line_items( $quote_id, $items );

		$saved_items = WAQ_Quote_Post_Type::get_line_items( $quote_id );
		WAQ_Quote_Post_Type::save_totals( $quote_id, WAQ_Calculator::calculate( $saved_items ) );

		wp_update_post(
			array(
				'ID'         => $quote_id,
				'post_title' => $customer_name ?: __( 'Untitled quote', 'woo-ai-quotes' ),
			)
		);

		if ( $finalize ) {
			WAQ_Quote_Post_Type::assign_quote_number( $quote_id );
			wp_update_post(
				array(
					'ID'          => $quote_id,
					'post_status' => 'publish',
				)
			);
			wp_safe_redirect( self::download_url( $quote_id ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=waq-edit-quote&quote_id=' . $quote_id . '&waq_notice=saved' ) );
		exit;
	}

	public static function handle_download_pdf() {
		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		check_admin_referer( 'waq_download_' . $quote_id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-ai-quotes' ) );
		}
		$quote = get_post( $quote_id );
		if ( ! $quote || WAQ_Quote_Post_Type::POST_TYPE !== $quote->post_type ) {
			wp_die( esc_html__( 'Quote not found.', 'woo-ai-quotes' ) );
		}
		WAQ_PDF::stream( $quote_id );
	}

	public static function handle_delete_quote() {
		$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
		check_admin_referer( 'waq_delete_' . $quote_id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-ai-quotes' ) );
		}
		wp_trash_post( $quote_id );
		wp_safe_redirect( admin_url( 'admin.php?page=waq-quotes&waq_notice=deleted' ) );
		exit;
	}

	public static function ajax_get_product() {
		check_ajax_referer( 'waq_get_product', 'nonce' );
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

	private static function download_url( $quote_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=waq_download_pdf&quote_id=' . $quote_id ), 'waq_download_' . $quote_id );
	}

	private static function delete_url( $quote_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=waq_delete_quote&quote_id=' . $quote_id ), 'waq_delete_' . $quote_id );
	}

	private static function render_notice() {
		$notice = isset( $_GET['waq_notice'] ) ? sanitize_key( $_GET['waq_notice'] ) : '';
		if ( 'ai_error' === $notice ) {
			$message = get_transient( 'waq_notice_' . get_current_user_id() );
			delete_transient( 'waq_notice_' . get_current_user_id() );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ?: __( 'The AI draft failed.', 'woo-ai-quotes' ) ) );
		} elseif ( 'saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote saved.', 'woo-ai-quotes' ) . '</p></div>';
		} elseif ( 'drafted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Draft created — review the line items below before finalizing.', 'woo-ai-quotes' ) . '</p></div>';
		} elseif ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote deleted.', 'woo-ai-quotes' ) . '</p></div>';
		}
	}
}
