/* global jQuery */
(function ($, undefined) {

	if (undefined === payplug_admin_config) {
		return;
	}

	var payplug_admin = {
		init: function () {
			this.checkbancontact();
			$('input[name=woocommerce_payplug_mode]').on('click', this.checkbancontact); //mode

		},
		checkBancontactPermissions: (callback) => {
			payplug_admin.xhr = $
				.post(
					payplug_admin_config.ajax_url,
					{
						action: 'check_bancontact_permissions',
						livekey: $('#woocommerce_payplug_payplug_live_key').length ? $('#woocommerce_payplug_payplug_live_key').val() : ''
					}
				).done((res) => { callback(res) });
		},
		checkbancontact: (event)=> {

			payplug_admin.disableBancontact();

			if(payplug_admin.isTestMode()){
				payplug_admin.uncheckBancontact();
				payplug_admin.disableBancontact();
				$("#bancontact_test_mode_description").show();
				$("#bancontact_call_to_action").hide();
				$("#bancontact_live_mode_description_disabled").hide();
				return;
			}
			$("#bancontact_test_mode_description").hide();
			$("#bancontact_call_to_action").show();

			payplug_admin.checkBancontactPermissions((res) => {
				if(false === res.success){
					payplug_admin.uncheckBancontact();
					payplug_admin.disableBancontact();
					$("#bancontact_live_mode_description_disabled").show();
					return;
				}

				if(false === res.data){
					payplug_admin.uncheckBancontact();
					payplug_admin.disableBancontact();
					$("#bancontact_live_mode_description_disabled").show();
					return;
				}

				payplug_admin.enableBancontact();
			});

		},
		isTestMode: function(){
			return jQuery("#woocommerce_payplug_mode-no").prop("checked");
		},
		uncheckBancontact: function(){
			jQuery("#woocommerce_payplug_bancontact").prop("checked", false);
		},
		disableBancontact: function(){
			jQuery("#woocommerce_payplug_bancontact").prop("disabled", true);
		},
		enableBancontact: function(){
			jQuery("#woocommerce_payplug_bancontact").prop("disabled", false);
			jQuery("#bancontact_live_mode_description_disabled").hide();
		}
	}
	payplug_admin.init();

})(jQuery);
