/* global YSQD_Editor, jQuery */
( function ( $ ) {
	'use strict';

	var $body = $( '#ysqd-items-body' );
	var rowIndex = $body.find( '.ysqd-item-row' ).length;

	function currency( amount ) {
		return YSQD_Editor.currencySymbol + amount.toFixed( 2 );
	}

	function recalcPreview() {
		var subtotal = 0;
		var tax = 0;

		$body.find( '.ysqd-item-row' ).each( function () {
			var $row = $( this );
			var qty = parseFloat( $row.find( '.ysqd-qty' ).val() ) || 0;
			var price = parseFloat( $row.find( '.ysqd-unit-price' ).val() ) || 0;
			var rateInput = $row.find( '.ysqd-tax-rate' ).val();
			var rate = '' === rateInput ? 0 : parseFloat( rateInput ) || 0;
			var lineTotal = qty * price;

			subtotal += lineTotal;
			tax += lineTotal * ( rate / 100 );
		} );

		$( '#ysqd-preview-subtotal' ).text( currency( subtotal ) );
		$( '#ysqd-preview-tax' ).text( currency( tax ) );
		$( '#ysqd-preview-total' ).text( currency( subtotal + tax ) );
	}

	function addRow( values ) {
		var template = document.getElementById( 'ysqd-row-template' ).innerHTML;
		var html = template.replace( /__INDEX__/g, rowIndex );
		rowIndex += 1;

		var $row = $( html );
		if ( values ) {
			$row.find( '.ysqd-product-id' ).val( values.product_id || 0 );
			$row.find( '.ysqd-description' ).val( values.description || '' );
			$row.find( '.ysqd-qty' ).val( values.qty || 1 );
			$row.find( '.ysqd-unit-price' ).val( values.price || 0 );
		}
		$body.append( $row );
		recalcPreview();
	}

	$( '#ysqd-add-custom-row' ).on( 'click', function () {
		addRow( null );
	} );

	$body.on( 'click', '.ysqd-remove-row', function () {
		$( this ).closest( 'tr' ).remove();
		recalcPreview();
	} );

	$body.on( 'input', '.ysqd-qty, .ysqd-unit-price, .ysqd-tax-rate', recalcPreview );

	$( '#ysqd-add-product' ).on( 'select2:select', function ( e ) {
		var productId = e.params.data.id;
		if ( ! productId ) {
			return;
		}

		$.post( YSQD_Editor.ajaxUrl, {
			action: 'ysqd_get_product',
			nonce: YSQD_Editor.nonce,
			product_id: productId,
		} ).done( function ( response ) {
			if ( response && response.success ) {
				addRow( {
					product_id: response.data.id,
					description: response.data.name,
					qty: 1,
					price: response.data.price,
				} );
			}
			$( '#ysqd-add-product' ).val( null ).trigger( 'change' );
		} );
	} );

	recalcPreview();
} )( jQuery );
