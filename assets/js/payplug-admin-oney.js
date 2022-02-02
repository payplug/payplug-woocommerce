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
			if ($("#woocommerce_payplug_oney").length) {
				pao.$payplug_oney = $("#woocommerce_payplug_oney")
			}
			if ($('input[name=woocommerce_payplug_mode]').length) {
				pao.$payplug_mode = $('input[name=woocommerce_payplug_mode]:checked').val()
				$('input[name=woocommerce_payplug_mode]').on(
					'click',
					pao.toggleMode
				)
			}

			pao.$payplug_oney.on('change', function()  {
				if (pao.$payplug_oney.prop('checked')) {
					if (1 == pao.$payplug_mode) {
						pao.verifyOney()
					}
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
					} else {
						pao.$payplug_oney.prop('checked', true)
					}
				})
				.fail((res) => {
					pao.$payplug_oney.prop('disabled', false)
				})
		},
		toggleMode: (event) => {
			pao.$payplug_mode = $('input[name=woocommerce_payplug_mode]:checked').val()
			if (pao.$payplug_oney.prop('checked')) {
				pao.verifyOney()
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
