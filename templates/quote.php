<?php
/**
 * Quote PDF template. Rendered via output buffering in WAQ_PDF::build_html()
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
<style>
	body {
		font-family: 'DejaVu Sans', sans-serif;
		font-size: 11px;
		color: #26302c;
	}
	.header {
		width: 100%;
		border-bottom: 2px solid #26302c;
		padding-bottom: 12px;
		margin-bottom: 18px;
	}
	.header table { width: 100%; border-collapse: collapse; }
	.header td { vertical-align: top; }
	.logo img { max-height: 50px; }
	.company-name { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
	.company-details { font-size: 9.5px; color: #55605c; line-height: 1.5; }
	.doc-title { font-size: 22px; font-weight: bold; text-align: right; letter-spacing: 1px; }
	.doc-meta { text-align: right; font-size: 9.5px; color: #55605c; margin-top: 6px; line-height: 1.6; }
	.customer-block { margin-bottom: 18px; }
	.customer-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #8a938e; margin-bottom: 3px; }
	.customer-name { font-size: 12px; font-weight: bold; }
	table.items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
	table.items th {
		text-align: left;
		font-size: 8.5px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: #8a938e;
		border-bottom: 1px solid #cfd6d0;
		padding: 6px 4px;
	}
	table.items td {
		padding: 7px 4px;
		border-bottom: 1px solid #e6e9e5;
		font-size: 10.5px;
	}
	table.items .num { text-align: right; }
	.totals { width: 260px; float: right; margin-top: 10px; }
	.totals table { width: 100%; border-collapse: collapse; }
	.totals td { padding: 4px 0; font-size: 10.5px; }
	.totals .label { color: #55605c; }
	.totals .num { text-align: right; }
	.totals .grand td { border-top: 1px solid #26302c; padding-top: 7px; font-weight: bold; font-size: 12px; }
	.footer { clear: both; margin-top: 60px; padding-top: 14px; border-top: 1px solid #e6e9e5; font-size: 9px; color: #8a938e; line-height: 1.6; }
	.footer .payment { margin-bottom: 10px; white-space: pre-line; }
</style>
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
					<div class="doc-title"><?php esc_html_e( 'QUOTE', 'woo-ai-quotes' ); ?></div>
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
		<div class="customer-label"><?php esc_html_e( 'Prepared for', 'woo-ai-quotes' ); ?></div>
		<div class="customer-name"><?php echo esc_html( $customer['name'] ); ?></div>
		<?php if ( ! empty( $customer['email'] ) ) : ?>
			<div class="company-details"><?php echo esc_html( $customer['email'] ); ?></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="items">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Description', 'woo-ai-quotes' ); ?></th>
				<th class="num"><?php esc_html_e( 'Qty', 'woo-ai-quotes' ); ?></th>
				<th class="num"><?php esc_html_e( 'Unit price', 'woo-ai-quotes' ); ?></th>
				<th class="num"><?php esc_html_e( 'Line total', 'woo-ai-quotes' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $line_items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item['description'] ); ?></td>
					<td class="num"><?php echo esc_html( rtrim( rtrim( number_format( (float) $item['qty'], 2 ), '0' ), '.' ) ); ?></td>
					<td class="num"><?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?></td>
					<td class="num"><?php echo wp_kses_post( wc_price( (float) $item['unit_price'] * (float) $item['qty'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="totals">
		<table>
			<tr>
				<td class="label"><?php esc_html_e( 'Subtotal', 'woo-ai-quotes' ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $totals['subtotal'] ) ); ?></td>
			</tr>
			<tr>
				<td class="label"><?php esc_html_e( 'Tax', 'woo-ai-quotes' ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $totals['tax'] ) ); ?></td>
			</tr>
			<tr class="grand">
				<td><?php esc_html_e( 'Total', 'woo-ai-quotes' ); ?></td>
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
