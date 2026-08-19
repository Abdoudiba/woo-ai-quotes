<?php
/**
 * Quote PDF template. Rendered via output buffering in YSQD_PDF::build_html()
 * with these variables in scope: $company, $customer, $line_items, $totals,
 * $quote_number, $date, $payment_details, $footer, $currency_symbol.
 *
 * Plain HTML/CSS for dompdf — no external assets besides an optional logo
 * URL, no webfonts (dompdf's bundled DejaVu Sans covers accented characters
 * without a network fetch).
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<?php
/**
 * The stylesheet lives in its own file, assets/css/quote-pdf.css, and its
 * contents are read and echoed here in PHP rather than written as literal
 * template HTML — this markup is never served as a WordPress page (no
 * wp_head/wp_enqueue_scripts hook fires for it, it's a standalone string
 * fed straight into Dompdf::loadHtml() to rasterize a PDF), so
 * wp_enqueue_style() has no page-load context to ever actually print a
 * registered handle into.
 */
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- local plugin asset, not user input; no filesystem API is available/appropriate in this non-admin PDF-render context.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS, not HTML, from a static file this plugin ships; esc_html() would corrupt selectors like `>` and is the wrong escaping context for a stylesheet body.
echo '<style>' . file_get_contents( YSQD_PLUGIN_DIR . 'assets/css/quote-pdf.css' ) . '</style>';
?>
</head>
<body>

	<div class="header">
		<table>
			<tr>
				<td style="width: 55%;">
					<?php if ( ! empty( $company['logo'] ) ) : ?>
						<div class="logo"><img src="<?php echo esc_url( $company['logo'] ); ?>" alt=""></div>
					<?php endif; ?>
					<div class="company-name"><?php echo esc_html( $company['name'] ); ?></div>
					<div class="company-details">
						<?php echo nl2br( esc_html( $company['address'] ) ); ?><br>
						<?php if ( ! empty( $company['phone'] ) ) : ?><?php echo esc_html( $company['phone'] ); ?><br><?php endif; ?>
						<?php if ( ! empty( $company['email'] ) ) : ?><?php echo esc_html( $company['email'] ); ?><?php endif; ?>
					</div>
				</td>
				<td style="width: 45%;">
					<div class="doc-title"><?php esc_html_e( 'QUOTE', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></div>
					<div class="doc-meta">
						<?php echo esc_html( $quote_number ); ?><br>
						<?php echo esc_html( $date ); ?>
					</div>
				</td>
			</tr>
		</table>
	</div>

	<?php if ( ! empty( $customer['name'] ) ) : ?>
	<div class="customer-block">
		<div class="customer-label"><?php esc_html_e( 'Prepared for', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></div>
		<div class="customer-name"><?php echo esc_html( $customer['name'] ); ?></div>
		<?php if ( ! empty( $customer['email'] ) ) : ?>
			<div class="company-details"><?php echo esc_html( $customer['email'] ); ?></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="items">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Description', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
				<th class="num"><?php esc_html_e( 'Qty', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
				<th class="num"><?php esc_html_e( 'Unit price', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
				<th class="num"><?php esc_html_e( 'Line total', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $line_items as $ysqd_item ) : ?>
				<tr>
					<td><?php echo esc_html( $ysqd_item['description'] ); ?></td>
					<td class="num"><?php echo esc_html( rtrim( rtrim( number_format( (float) $ysqd_item['qty'], 2 ), '0' ), '.' ) ); ?></td>
					<td class="num"><?php echo wp_kses_post( wc_price( $ysqd_item['unit_price'] ) ); ?></td>
					<td class="num"><?php echo wp_kses_post( wc_price( (float) $ysqd_item['unit_price'] * (float) $ysqd_item['qty'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="totals">
		<table>
			<tr>
				<td class="label"><?php esc_html_e( 'Subtotal', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $totals['subtotal'] ) ); ?></td>
			</tr>
			<tr>
				<td class="label"><?php esc_html_e( 'Tax', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $totals['tax'] ) ); ?></td>
			</tr>
			<tr class="grand">
				<td><?php esc_html_e( 'Total', 'yuupee-smart-quote-drafting-for-woocommerce' ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></td>
			</tr>
		</table>
	</div>

	<div class="footer">
		<?php if ( ! empty( $payment_details ) ) : ?>
			<div class="payment"><?php echo esc_html( $payment_details ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $footer ) ) : ?>
			<div><?php echo nl2br( esc_html( $footer ) ); ?></div>
		<?php endif; ?>
	</div>

</body>
</html>
