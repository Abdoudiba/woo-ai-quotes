/* global WAQ_Editor, jQuery */
( function ( $ ) {
	'use strict';

	var $body = $( '#waq-items-body' );
	var rowIndex = $body.find( '.waq-item-row' ).length;

	function currency( amount ) {
		return WAQ_Editor.currencySymbol + amount.toFixed( 2 );
	}

	function recalcPreview() {
		var subtotal = 0;
		var tax = 0;

		$body.find( '.waq-item-row' ).each( function () {
			var $row = $( this );
			var qty = parseFloat( $row.find( '.waq-qty' ).val() ) || 0;
			var price = parseFloat( $row.find( '.waq-unit-price' ).val() ) || 0;
			var rateInput = $row.find( '.waq-tax-rate' ).val();
			var rate = '' === rateInput ? 0 : parseFloat( rateInput ) || 0;
			var lineTotal = qty * price;

			subtotal += lineTotal;
			tax += lineTotal * ( rate / 100 );
		} );

		$( '#waq-preview-subtotal' ).text( currency( subtotal ) );
		$( '#waq-preview-tax' ).text( currency( tax ) );
		$( '#waq-preview-total' ).text( currency( subtotal + tax ) );
	}

	function addRow( values ) {
		var template = document.getElementById( 'waq-row-template' ).innerHTML;
		var html = template.replace( /__INDEX__/g, rowIndex );
		rowIndex += 1;

		var $row = $( html );
		if ( values ) {
			$row.find( '.waq-product-id' ).val( values.product_id || 0 );
			$row.find( '.waq-description' ).val( values.description || '' );
			$row.find( '.waq-qty' ).val( values.qty || 1 );
			$row.find( '.waq-unit-price' ).val( values.price || 0 );
		}
		$body.append( $row );
		recalcPreview();
	}

	$( '#waq-add-custom-row' ).on( 'click', function () {
		addRow( null );
	} );

	$body.on( 'click', '.waq-remove-row', function () {
		$( this ).closest( 'tr' ).remove();
		recalcPreview();
	} );

	$body.on( 'input', '.waq-qty, .waq-unit-price, .waq-tax-rate', recalcPreview );

	$( '#waq-add-product' ).on( 'select2:select', function ( e ) {
		var productId = e.params.data.id;
		if ( ! productId ) {
			return;
		}

		$.post( WAQ_Editor.ajaxUrl, {
			action: 'waq_get_product',
			nonce: WAQ_Editor.nonce,
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
			$( '#waq-add-product' ).val( null ).trigger( 'change' );
		} );
	} );

	recalcPreview();
} )( jQuery );
