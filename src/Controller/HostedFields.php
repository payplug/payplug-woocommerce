<?php

namespace Payplug\PayplugWoocommerce\Controller;

class HostedFields
{
    public static function template_form($save_card)
    {
        $f = fn ($fn) => $fn;

        if ($save_card) {
            $saved = <<<HTML
					<div class="payplug HostedFields_container -saveCard" data-e2e-name="saveCard">
						<label><input type="checkbox" name="savecard"><span></span>{$f(__('payplug_integrated_payment_oneClick', 'payplug'))}</label>
					</div>
HTML;
        } else {
            $saved = '';
        }

        return <<<HTML
			<form class="payplug HostedFields -loaded">
				<input type="hidden" name="hf_token" id="hf-token" value="" />
				<input type="hidden" name="hf_selected_brand" id="hf-selected-brand" value="" />
				<div class="payplug HostedFields_container -brand" id="hosted-fields-brand" data-e2e-name="brand"></div>
				<div class="payplug HostedFields_container -card" id="hosted-fields-card" data-e2e-name="card"></div>
				<div class="payplug HostedFields_error -card -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_hosted_fields_card_error', 'payplug'))}</span>
				</div>
				<div class="payplug HostedFields_container -expiry" id="hosted-fields-expiry" data-e2e-name="expiry"></div>
				<div class="payplug HostedFields_error -expiry -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_hosted_fields_expiry_error', 'payplug'))}</span>
				</div>
				<div class="payplug HostedFields_container -cryptogram" id="hosted-fields-cryptogram" data-e2e-name="cryptogram"></div>
				<div class="payplug HostedFields_error -cryptogram -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_hosted_fields_cryptogram_error', 'payplug'))}</span>
				</div>

				{$saved}

				<div class="payplug HostedFields_error -tokenization -hide">
					<span class="genericError">{$f(__('payplug_hosted_fields_tokenization_error', 'payplug'))}</span>
					<span class="brandError -hide">{$f(__('payplug_hosted_fields_unsupported_brand_error', 'payplug'))}</span>
				</div>
			</form>
HTML;
    }
}
