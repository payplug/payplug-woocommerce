/* global jQuery */

(($, undefined) => {

	var pao = {
		init: function()  {
			// setup modal
			pao.$dialogoney = $('#payplug-oney-modal').dialog({
				autoOpen: false,
				modal: true,
				closeText : "",
				draggable: false,
				buttons: [
					{
						class: "ui-dialog-submit",
						text: payplug_admin_config.btn_ok,
						click: pao.onDialogOneyClose
					}
				],
				show: true,
				hide: 100
			})
			pao.$yes_no_description = $("#live-mode-test-p");
			if ($("#woocommerce_payplug_oney").length) {
				pao.$payplug_oney = $("#woocommerce_payplug_oney")
				pao.$payplug_oney_type = $("#woocommerce_payplug_oney_type, #woocommerce_payplug_oney_thresholds")
				if (pao.$payplug_oney.prop('checked')) {
					pao.$payplug_oney_type.css('display', 'table-row')
				} else {
					pao.$payplug_oney_type.css('display', 'none')
				}
			}
			if ($('input[name=woocommerce_payplug_mode]').length) {
				pao.$payplug_mode = $('input[name=woocommerce_payplug_mode]:checked').val()
				pao.showHideDescription();
				$('input[name=woocommerce_payplug_mode]').on(
					'click',
					pao.toggleMode
				)
			}

			pao.$payplug_oney.on('change', function()  {
				if (pao.$payplug_oney.prop('checked')) {
					if (1 == pao.$payplug_mode) {
						pao.verifyOney()
					} else {
						pao.$payplug_oney_type.css('display', 'table-row')
					}
					$("[for=woocommerce_payplug_oney]").siblings('p.description').hide()

				} else {
					pao.$payplug_oney_type.css('display', 'none')
					$("[for=woocommerce_payplug_oney]").siblings('p.description').show()
				}
			})
			pao.init_oney_thresholds();

		},
		verifyOney: function()  {
			pao.$payplug_oney.prop('disabled', true)
			pao.xhr = $
				.post(
					payplug_admin_config.ajax_url,
					{
						action: 'check_live_permissions',
						livekey : $('#woocommerce_payplug_payplug_live_key').length ? $('#woocommerce_payplug_payplug_live_key').val() : ''
					}
				).done((res) => {
					pao.is_oney_refresh = false
					pao.$payplug_oney.prop('disabled', false)
					pao.xhr = false
					if (false === res.success) {
						alert(res.data.error)
						setTimeout(() => pao.$payplug_oney.prop('checked', false), 200)
					}
					if (false === res.data.can_use_oney) {
						pao.$dialogoney.dialog('open')
						setTimeout(() => pao.$payplug_oney.prop('checked', false), 200)
						pao.$payplug_oney_type.css('display', 'none')
					} else {
						pao.$payplug_oney.prop('checked', true)
						pao.$payplug_oney_type.css('display', 'table-row')
					}
				})
				.fail((res) => {
					pao.$payplug_oney.prop('disabled', false)
					pao.$payplug_oney_type.css('display', 'none')
				})
		},
		toggleMode: (event) => {
			pao.$payplug_mode = $('input[name=woocommerce_payplug_mode]:checked').val()
			pao.showHideDescription();
			if (pao.$payplug_oney.prop('checked')) {
				if(payplug_admin_config.has_live_key)
					pao.verifyOney()
			}
		},
		showHideDescription: function(){
			pao.$payplug_mode = $('input[name=woocommerce_payplug_mode]:checked').val()
			if(pao.$payplug_mode == 1){
				pao.$yes_no_description.css('display', 'none');
			}else{
				pao.$yes_no_description.css('display', 'block');
			}
		},
		onDialogOneyClose: function()  {
			pao.$dialogoney.dialog('close')
			if (pao.is_oney_refresh) {
				window.location.reload()
			}
		},

		init_oney_thresholds: function () {

			var min = Number(payplug_admin_config.min_oney_price);
			var max = Number(payplug_admin_config.max_oney_price);

			var $hidden_min_input = $("#woocommerce_payplug_oney_thresholds_min");
			var $hidden_max_input = $("#woocommerce_payplug_oney_thresholds_max");

			var $min_input = $("#payplug_oney_thresholds_min");
			var $max_input = $("#payplug_oney_thresholds_max");

			$min_input.val(Number($hidden_min_input.val()));
			$max_input.val(Number($hidden_max_input.val()));

			$( "#slider-range" ).slider({
				range: true,
				min: min,
				max: max,
				step: 10,
				values: [payplug_admin_config.oney_thresholds_min, payplug_admin_config.oney_thresholds_max],
				slide: function( event, ui ) {

					$hidden_min_input.val( ui.values[ 0 ] );
					$hidden_max_input.val( ui.values[ 1 ]);

					$min_input.val( Number(ui.values[ 0 ]) );
					$max_input.val( Number(ui.values[ 1 ]) );

					$("#oney_thresholds_description .min, #slider-range .ui-slider-handle:first-of-type .tooltip").text( ui.values[ 0 ] + '€');
					$("#oney_thresholds_description .max, #slider-range .ui-slider-handle:last-of-type .tooltip").text( ui.values[ 1 ] + '€');
				}
			});

			$("#payplug_oney_thresholds_min, #payplug_oney_thresholds_max").on( "change", function() {

				if( Number($max_input.val()) < Number($min_input.val()) ){
					$max_input.val( max );
					$min_input.val( min );
				}

				if( $min_input.val() < min )
					$min_input.val(min);

				if( $max_input.val() > max )
					$max_input.val( max );

				//slider values
				$( "#slider-range"  ).slider( "option", "values", [ $min_input.val(), $max_input.val() ]);
				$("#oney_thresholds_description .min, #slider-range .ui-slider-handle:first-of-type .tooltip").text( $min_input.val() + '€');
				$("#oney_thresholds_description .max, #slider-range .ui-slider-handle:last-of-type .tooltip").text( $max_input.val() + '€');
				$("#woocommerce_payplug_oney_thresholds_min").val($min_input.val());
				$("#woocommerce_payplug_oney_thresholds_max").val($max_input.val());

			});

			$("#oney_thresholds_description").html($("#oney_thresholds_description").html().replaceAll("€", "<b>€</b>"))
			$('.ui-slider-handle').html('<span class="tooltip"></span>')
			$("#slider-range .ui-slider-handle:first-of-type .tooltip").html(payplug_admin_config.oney_thresholds_min + '€')
			$("#slider-range .ui-slider-handle:last-of-type .tooltip").html(payplug_admin_config.oney_thresholds_max + '€')

		}
	}
	pao.init()

})(jQuery)
