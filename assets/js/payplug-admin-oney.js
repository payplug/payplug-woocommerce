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
				pao.$payplug_oney_type = $("#woocommerce_payplug_oney_type")
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
				} else {
					pao.$payplug_oney_type.css('display', 'none')
				}
			})
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
		}
	}
	pao.init()
})(jQuery)
