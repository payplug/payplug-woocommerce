/* global jQuery */
(function ($, undefined) {

	if (undefined === payplug_admin_config) {
		return;
	}

	var payplug_admin = {
		init: function () {
			this.check_american_express();
			$('input[name=woocommerce_payplug_mode]').on('click', this.check_american_express); //mode

		},
		check_american_express_permissions: (callback) => {
			payplug_admin.xhr = $
				.post(
					payplug_admin_config.ajax_url,
					{
						action: 'check_american_express_permissions',
						livekey: $('#woocommerce_payplug_payplug_live_key').length ? $('#woocommerce_payplug_payplug_live_key').val() : ''
					}
				).done((res) => { callback(res) });
		},
		check_american_express: (event)=> {

			payplug_admin.disableamerican_express();

			if(payplug_admin.isTestMode()){
				payplug_admin.uncheck_american_express();
				payplug_admin.disableamerican_express();
				return;
			}

			payplug_admin.check_american_express_permissions((res) => {
				if(false === res.success){
					payplug_admin.uncheck_american_express();
					payplug_admin.disableamerican_express();
					return;
				}

				if(false === res.data){
					payplug_admin.uncheck_american_express();
					payplug_admin.disableamerican_express();
					return;
				}

				payplug_admin.enableamerican_express();
			});

		},
		isTestMode: function(){
			return jQuery("#woocommerce_payplug_mode-no").prop("checked");
		},
		uncheck_american_express: function(){
			jQuery("#woocommerce_payplug_american_express").prop("checked", false);
		},
		disableamerican_express: function(){
			jQuery("#woocommerce_payplug_american_express").prop("disabled", true);
		},
		enableamerican_express: function(){
			jQuery("#woocommerce_payplug_american_express").prop("disabled", false);
		}
	}
	payplug_admin.init();

})(jQuery);
