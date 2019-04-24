/* global jQuery */

(function ($, undefined) {

	if (undefined === payplug_admin_config) {
		return;
	}

	var payplug_admin = {
		init: function () {
			payplug_admin.xhr = false;

			payplug_admin.$dialog = $('#payplug-refresh-keys-modal').dialog({
				autoOpen: false,
				modal: true,
				buttons: [
					{
						text: "Ok",
						click: payplug_admin.refreshKeys
					},
					{
						text: "Cancel",
						click: payplug_admin.onCancelClick
					}
				],
				show: true,
				hide: 100,
				close: payplug_admin.onDialogClose
			});

			if ($('input[name=woocommerce_payplug_mode]').length) {
				this.$payplug_mode = $('input[name=woocommerce_payplug_mode]');
				this.$payplug_mode.on(
					'click',
					this.onClick
				)
			}
		},
		onClick: function (event) {
			if ('0' === event.currentTarget.value || payplug_admin_config.has_live_key) {
				// ignore event if user choose TEST mode or already has LIVE keys
				return;
			}

			payplug_admin.$dialog.dialog('open');
		},
		onCancelClick: function () {
			if (payplug_admin.xhr) {
				payplug_admin.xhr.abort();
			}

			payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').get(0).reset();

			if (payplug_admin_config.has_live_key && this.$payplug_mode.prop('checked')) {
				this.$payplug_mode.prop('checked', '');
			}

			payplug_admin.$dialog.dialog('close');
		},
		onDialogClose: function () {
			if (payplug_admin.xhr) {
				payplug_admin.xhr.abort();
			}

			payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').get(0).reset();

			if (payplug_admin_config.has_live_key && this.$payplug_mode.prop('checked')) {
				this.$payplug_mode.prop('checked', '');
			}
		},
		refreshKeys: function () {
			payplug_admin._lockDialog();
			var form = payplug_admin.$dialog.find('form#payplug-refresh-keys-modal__form').get(0);

			if (payplug_admin.xhr) {
				payplug_admin.xhr.abort();
			}

			payplug_admin.xhr = $
				.post(
					payplug_admin_config.ajax_url,
					$(form).serializeArray()
				).done(function (res) {
					console.log(res);
					form.reset();
					payplug_admin.xhr = false;
					payplug_admin._unlockDialog();
				})
				.fail(function (res) {
					console.log(res);
					form.reset();
					payplug_admin.xhr = false;
					payplug_admin._unlockDialog();
				});
		},
		_lockDialog: function () {
			var buttons = payplug_admin._disabledHelper(payplug_admin.$dialog.dialog('option', 'buttons'), true);
			payplug_admin.$dialog.dialog('option', 'buttons', buttons);
		},
		_unlockDialog: function () {
			var buttons = payplug_admin._disabledHelper(payplug_admin.$dialog.dialog('option', 'buttons'), false);
			payplug_admin.$dialog.dialog('option', 'buttons', buttons);
		},
		_disabledHelper(items, disabled) {
			return $.each(items, function (i, val) {
				val.disabled = disabled;
			});
		}
	};

	payplug_admin.init();
})(jQuery);
