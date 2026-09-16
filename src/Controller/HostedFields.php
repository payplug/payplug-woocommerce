<?php

namespace Payplug\PayplugWoocommerce\Controller;

class HostedFields
{
    /**
     * @param bool     $save_card whether the BO "one-click" flag is on for this gateway
     * @param object[] $cards     the current customer's saved UHF cards (Model\UhfCard::get_customer_cards() shape) - empty for a guest or when there are none
     */
    public static function template_form($save_card, array $cards = [])
    {
        // The transaction-secured/privacy-policy footer is visually identical to
        // Integrated Payment's, so it reuses IntegratedPayment_container classes and
        // assets directly rather than duplicating them under HostedFields_container.
        $logo = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
        $lock = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
        $privacy_policy_url = __('payplug_integrated_payment_privacy_policy_url', 'payplug');
        $f = fn ($fn) => $fn;

        $cards_html = '';
        foreach ($cards as $card) {
            $cards_html .= sprintf(
                '<label class="payplug HostedFields_savedCard"><input type="radio" name="payplug_uhf_card_choice" value="%d">%s &bull;&bull;&bull;&bull; %s &mdash; %02d/%d</label>',
                (int) $card->id,
                esc_html($card->brand),
                esc_html($card->last4),
                (int) $card->exp_month,
                (int) $card->exp_year
            );
        }

        if ('' !== $cards_html) {
            $cards_html .= sprintf(
                '<label class="payplug HostedFields_savedCard -other"><input type="radio" name="payplug_uhf_card_choice" value="other" checked>%s</label>',
                $f(__('payplug_hosted_fields_pay_with_another_card', 'payplug'))
            );
            $cards_html = '<div class="payplug HostedFields_container -savedCards" data-e2e-name="savedCards">' . $cards_html . '</div>';
        }

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
				{$cards_html}
				<input type="hidden" name="hf_token" id="hf-token" value="" />
				<input type="hidden" name="hf_selected_brand" id="hf-selected-brand" value="" />
				<input type="hidden" name="hf_last4" id="hf-last4" value="" />
				<input type="hidden" name="hf_expiration_month" id="hf-expiration-month" value="" />
				<input type="hidden" name="hf_expiration_year" id="hf-expiration-year" value="" />
				<div class="payplug HostedFields_container -brand" id="hosted-fields-brand" data-e2e-name="brand"></div>
				<div class="payplug HostedFields_container -card" id="hosted-fields-card" data-e2e-name="card"></div>
				<div class="payplug HostedFields_container -expiry" id="hosted-fields-expiry" data-e2e-name="expiry"></div>
				<div class="payplug HostedFields_container -cryptogram" id="hosted-fields-cryptogram" data-e2e-name="cryptogram"></div>

				{$saved}

				<div class="payplug HostedFields_error -tokenization -hide">
					<span class="genericError">{$f(__('payplug_hosted_fields_tokenization_error', 'payplug'))}</span>
					<span class="brandError -hide">{$f(__('payplug_hosted_fields_unsupported_brand_error', 'payplug'))}</span>
				</div>

				<div class="payplug IntegratedPayment_container -transaction">
					<img class="lock-icon" src="$lock" /><label class="transaction-label">{$f(__('payplug_integrated_payment_transaction_secure', 'payplug'))}</label><img class="payplug-logo" src="$logo" />
				</div>
				<div class="payplug IntegratedPayment_container -privacy-policy">
					<a href="$privacy_policy_url" target="_blank">{$f(__('payplug_integrated_payment_privacy_policy', 'payplug'))}</a>
				</div>
			</form>
HTML;
    }
}
