/* global jQuery */
(function ($, undefined) {

	if (undefined === payplug_admin_config) {
		return;
	}

	var payplug_admin = {
		init: function () {
			this.checkApplePay();
			$('input[name=woocommerce_payplug_mode]').on('click', this.checkApplePay); //mode

		},
		checkApplePayPermissions: (callback) => {
			payplug_admin.xhr = $
				.post(
					payplug_admin_config.ajax_url,
					{
						action: 'check_applepay_permissions',
						livekey: $('#woocommerce_payplug_payplug_live_key').length ? $('#woocommerce_payplug_payplug_live_key').val() : ''
					}
				).done((res) => { callback(res) });
		},
		checkApplePay: (event)=> {

			if(payplug_admin.isTestMode()){
				payplug_admin.uncheckApplePay();
				payplug_admin.disableApplePay();
				$("#apple_pay_test_mode_description").show();
				$("#apple_pay_call_to_action").hide();
				$("#apple_pay_live_mode_description").hide();
				return;
			} else {
				payplug_admin.enableApplePay();
				$("#apple_pay_test_mode_description").hide();
				$("#apple_pay_call_to_action").show();
				$("#apple_pay_live_mode_description").show();
			}


			payplug_admin.checkApplePayPermissions((res) => {
				if(false === res.success){
					payplug_admin.uncheckApplePay();
					payplug_admin.disableApplePay();
					return;
				}

				if(false === res.data){
					payplug_admin.uncheckApplePay();
					return;
				}

				payplug_admin.enableApplePay();
			});

		},
		isTestMode: function(){
			return jQuery("#woocommerce_payplug_mode-no").prop("checked");
		},
		uncheckApplePay: function(){
			jQuery("#woocommerce_payplug_apple_pay").prop("checked", false);
		},
		disableApplePay: function(){
			jQuery("#woocommerce_payplug_apple_pay").prop("disabled", true);
			if(!payplug_admin.isTestMode())
				jQuery("#apple_pay_live_mode_description_disabled").show()
		},
		enableApplePay: function(){
			jQuery("#woocommerce_payplug_apple_pay").prop("disabled", false);
			jQuery("#apple_pay_live_mode_description_disabled").hide();
		}
	}
	payplug_admin.init();

})(jQuery);
