<?php
defined( 'ABSPATH' ) || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a quote to PDF via dompdf, using the plain-PHP template in
 * templates/quote.php. No external HTTP calls at render time except an
 * optional logo image URL the site owner configured themselves.
 */
class YSQD_PDF {

	public static function stream( $quote_id ) {
		$pdf    = self::render( $quote_id );
		$number = YSQD_Quote_Post_Type::get_quote_number( $quote_id ) ?: 'draft';

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $number ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore -- raw binary PDF output, not HTML.
		exit;
	}

	public static function render( $quote_id ) {
		$options = new Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'defaultFont', 'DejaVu Sans' );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( self::build_html( $quote_id ) );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		return $dompdf->output();
	}

	private static function build_html( $quote_id ) {
		$company         = YSQD_Settings::get_company();
		$customer        = YSQD_Quote_Post_Type::get_customer( $quote_id );
		$line_items      = YSQD_Quote_Post_Type::get_line_items( $quote_id );
		$totals          = YSQD_Quote_Post_Type::get_totals( $quote_id );
		$quote_number    = YSQD_Quote_Post_Type::get_quote_number( $quote_id ) ?: __( 'DRAFT', 'yuupee-smart-quote-drafting-for-woocommerce' );
		$date            = get_the_date( '', $quote_id );
		$payment_details = YSQD_Settings::get_payment_details();
		$footer          = YSQD_Settings::get_quote_footer();

		ob_start();
		include YSQD_PLUGIN_DIR . 'templates/quote.php';
		return ob_get_clean();
	}
}
