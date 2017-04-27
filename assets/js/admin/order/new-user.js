if (typeof jQuery != 'undefined') (function($, undefined) {
	var d = $(document); /// THIS.... is dumb
	var settings = _qsot_new_user || {};
	var qt = QS.Tools;

	if (typeof settings.templates != 'object') return;
	var form = undefined;

	// dumb we have to do this with select2 v4
	function _select2_update_data( control, data, select_value ) {
		// empty the select element
		control.find( 'option' ).remove();

		data = qt.isO( data ) && qt.is( data.results ) ? data.results : data;
		// add a new item to the element for each possible value
		$.each( data, function( k, row ) {
			$( '<option></option>' ).html( row.text ).attr( 'value', row.id ).appendTo( control );
		} );

		// if the select value is set, use it
		if ( select_value )
			control.val( select_value );

		// refresh the select2 element
		control.trigger( 'change' );
	}
		
	function load_customer_billing_information() {
		// Get user ID to load data for
		var user_id = $('#customer_user').val();

		if (!user_id) {
			alert(woocommerce_admin_meta_boxes.no_customer_selected);
			return false;
		}

		var data = {
			user_id: 			user_id,
			type_to_load: 		'billing',
			action: 			'woocommerce_get_customer_details',
			security: 			woocommerce_admin_meta_boxes.get_customer_details_nonce
		};

		$('.edit_address').block({ message: null, overlayCSS: { background: '#fff url(' + woocommerce_admin_meta_boxes.plugin_url + '/assets/images/select2-spinner.gif) no-repeat center', opacity: 0.6 } });

		$.ajax({
			url: woocommerce_admin_meta_boxes.ajax_url,
			data: data,
			type: 'POST',
			success: function( response ) {
				var info = response;
				console.log( 'loaded billing', info );

				if (info && info.billing) {
					$('input#_billing.first_name').val( info.billing.first_name );
					$('input#_billing.last_name').val( info.billing.last_name );
					$('input#_billing.company').val( info.billing.company );
					$('input#_billing.address_1').val( info.billing.address_1 );
					$('input#_billing.address_2').val( info.billing.address_2 );
					$('input#_billing.city').val( info.billing.city );
					$('input#_billing.postcode').val( info.billing.postcode );
					$('#_billing.country').val( info.billing.country );
					$('input#_billing.state').val( info.billing.state );
					$('input#_billing.email').val( info.billing.email );
					$('input#_billing.phone').val( info.billing.phone );
				} else if ( info && info.billing_first_name ) {
					$('input#_billing_first_name').val( info.billing_first_name );
					$('input#_billing_last_name').val( info.billing_last_name );
					$('input#_billing_company').val( info.billing_company );
					$('input#_billing_address_1').val( info.billing_address_1 );
					$('input#_billing_address_2').val( info.billing_address_2 );
					$('input#_billing_city').val( info.billing_city );
					$('input#_billing_postcode').val( info.billing_postcode );
					$('#_billing_country').val( info.billing_country );
					$('input#_billing_state').val( info.billing_state );
					$('input#_billing_email').val( info.billing_email );
					$('input#_billing_phone').val( info.billing_phone );
				}

				$('.edit_address').unblock();
			}
		});
	}

	$(document).on('ajaxSuccess', function(ajObj, respObj, reqObj, resp) {
		if (typeof reqObj != 'undefined' && reqObj.data && reqObj.data.match(/action=woocommerce_get_customer_details/)) {
			if (reqObj.data.match(/type_to_load=billing/)) {
				$('.billing-sync-customer-address').attr('checked', 'checked');
			} else if (reqObj.data.match(/type_to_load=shipping/)) {
				$('.shipping-sync-customer-address').attr('checked', 'checked');
			}
		}
	});

	$(function() {
		$('button.billing-same-as-shipping').unbind('click').click(function(){
			var answer = confirm(woocommerce_admin_meta_boxes.copy_billing);
			if (answer){
				$('input#_shipping_first_name').val( $('input#_billing_first_name').val() );
				$('input#_shipping_last_name').val( $('input#_billing_last_name').val() );
				$('input#_shipping_company').val( $('input#_billing_company').val() );
				$('input#_shipping_address_1').val( $('input#_billing_address_1').val() );
				$('input#_shipping_address_2').val( $('input#_billing_address_2').val() );
				$('input#_shipping_city').val( $('input#_billing_city').val() );
				$('input#_shipping_postcode').val( $('input#_billing_postcode').val() );
				$('#_shipping_country').val( $('#_billing_country').val() );
				$('input#_shipping_state').val( $('input#_billing_state').val() );
				$('.shipping-sync-customer-address').attr('checked', 'checked');
			}
			return false;//// prevent form from submitting.... bad method. fix it
		});
	});

	$(function() {
		d.on('select2:selecting data-updated', '#customer_user', function() { 
			if ($(this).val() != '') {
				$('._billing_first_name_field').closest('.order_data_column').find('a.edit_address').click();//.closest('.order_data_column').find('button.load_customer_billing').click();
				load_customer_billing_information();
			}
		});
	});
	
	d.on('click', '[rel="new-user-btn"]', function(e) {
		e.preventDefault();
		if (typeof form == 'undefined') form = $(settings.templates['new-user-form']).appendTo('body').dialog({
			appendTo: 'body',
			autoOpen: false,
			dialogClass: 'new-user-dialog',
			width: 400,
			maxWidth: 400,
			minWidth: 400,
			modal: true,
			position: {
				my: 'center',
				at: 'center',
				of: window
			},
			title: 'New User',
			buttons: {
				'Create New User': function(e) {
					var dia = form.closest('.ui-dialog');

					function ajsuccess(r) {
						dia.unblock();
						if (r.s) {
							_select2_update_data( $( '#customer_user' ), [r.c], r.c.id );
							$( '#customer_user' ).trigger( 'data-updated' );
							form.dialog('close');
							form.find('input').val('');
							form.find('[rel=messages]').empty();
						} else {
							var msg = form.find('[rel=messages]').empty();
							if (r.e) for (var i=0; i<r.e.length; i++) $('<div class="err">ERROR: '+r.e[i]+'</div>').appendTo(msg);
							if (r.m) for (var i=0; i<r.m.length; i++) $('<div class="msg">MESSAGE: '+r.m[i]+'</div>').appendTo(msg);
						}
					};

					function ajerror(jqxhr, stts, err) {
						$('<div class="error">An unknown error ('+err+') occured while creating the user. Please try again later.</div>').appendTo(form.find('[rel=messages]').empty());
						dia.unblock();
					};

					dia.block({ overlayCSS:{backgroundColor:'#fff'} });
					var data = form.louSerialize();
					data.action = 'qsot-new-user';
					data.sa = 'create';
					data.new_user_email = data.new_user_email.replace(/\+/, '%2B');
					$.ajax({
						type: "POST",
						url: ajaxurl,
						data: data,
						success: ajsuccess,
						error: ajerror,
						dataType: 'json'
					});
				},
				'Cancel': function(e) { form.dialog('close'); }
			}
		});
		form.find('[messages]').empty();

		form.find('input').keypress(function(e) {
			if (e.keyCode == $.ui.keyCode.ENTER) {
				form.closest('.ui-dialog').find('.ui-dialog-buttonpane button:eq(0)').click();
			}
		});
		form.dialog('open');
	});
})(jQuery);
