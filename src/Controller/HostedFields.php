<?php

namespace Payplug\PayplugWoocommerce\Controller;

class HostedFields
{
    /**
     * @param bool $save_card whether the BO "one-click" flag is on for this gateway
     * @param object[] $cards the current customer's saved UHF cards (Model\UhfCard::get_customer_cards() shape) - empty for a guest or when there are none
     */
    public static function template_form($save_card, array $cards = [])
    {
        // Same markup, classes and assets as the PrestaShop module's hosted_fields.tpl, so the UHF
        // card form looks the same on both platforms (styles in assets/css/payplug-card-form.css).
        // The wrapper is a <div>, not a <form>: it is rendered inside WooCommerce's own checkout
        // (or order-pay) form, and the browser drops a nested <form> element altogether.
        $logo = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/integrated/payplug.svg';
        $lock = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/integrated/lock.svg';
        $privacy_policy_url = esc_url(__('payplug_integrated_payment_privacy_policy_url', 'payplug'));
        $f = fn ($fn) => $fn;

        $cards_html = '';
        foreach ($cards as $card) {
            $cards_html .= sprintf(
                '<label class="payplug HostedFields_savedCard"><input type="radio" name="payplug_uhf_card_choice" value="%d"><span></span>%s &bull;&bull;&bull;&bull; %s &mdash; %02d/%d</label>',
                (int) $card->id,
                esc_html($card->brand),
                esc_html($card->last4),
                (int) $card->exp_month,
                (int) $card->exp_year
            );
        }

        if ('' !== $cards_html) {
            $cards_html .= sprintf(
                '<label class="payplug HostedFields_savedCard -other"><input type="radio" name="payplug_uhf_card_choice" value="other" checked><span></span>%s</label>',
                $f(__('payplug_hosted_fields_pay_with_another_card', 'payplug'))
            );
            // A sibling block, not nested inside the <form> below: this mirrors how a
            // customer's saved payment methods always render as their own section,
            // separate from the "new card" fields - the same visual/structural split
            // Integrated Payment gets for free from WooCommerce's own native saved-token
            // list (see PayplugCreditCard::payment_fields()), which this plugin cannot
            // reuse here since a UHF alias is never a WC_Payment_Token. Keeping it out of
            // the form also means the form's own "-hide" toggle (assets/js/payplug-hosted-fields.js)
            // never hides the card choice itself when a saved card is selected.
            $cards_html = '<div class="payplug HostedFields_savedCards" data-e2e-name="savedCards">' . $cards_html . '</div>';
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
			{$cards_html}
			<div class="payplug HostedFields -loaded">
				<input type="hidden" name="hf_token" id="hf-token" value="" />
				<input type="hidden" name="hf_selected_brand" id="hf-selected-brand" value="" />
				<input type="hidden" name="hf_last4" id="hf-last4" value="" />
				<input type="hidden" name="hf_expiration_month" id="hf-expiration-month" value="" />
				<input type="hidden" name="hf_expiration_year" id="hf-expiration-year" value="" />

				<div class="payplug HostedFields_container -cardHolder">
					<input type="text" name="hf_cardholder" id="hf-cardholder" placeholder="{$f(esc_attr__('payplug_integrated_payment_cardholder', 'payplug'))}" autocomplete="cc-name" />
				</div>
				<div class="payplug HostedFields_error -cardHolder">
					<span class="-hide invalidField">{$f(esc_html__('payplug_integrated_payment_cardHolder_error', 'payplug'))}</span>
					<span class="-hide emptyField">{$f(esc_html__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>

				<div class="payplug HostedFields_container -scheme">
					<div>{$f(esc_html__('payplug_integrated_payment_your_card', 'payplug'))}</div>
					<div class="payplug HostedFields_schemes" id="hosted-fields-brand" data-e2e-name="brand"></div>
				</div>

				<div class="payplug HostedFields_container -pan" id="hosted-fields-card" data-e2e-name="card"></div>
				<div class="payplug HostedFields_error -pan">
					<span class="-hide invalidField">{$f(esc_html__('payplug_integrated_payment_pan_error', 'payplug'))}</span>
					<span class="-hide emptyField">{$f(esc_html__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>

				<div class="payplug HostedFields_container -exp" id="hosted-fields-expiry" data-e2e-name="expiry"></div>
				<div class="payplug HostedFields_container -cvv" id="hosted-fields-cryptogram" data-e2e-name="cryptogram"></div>
				<div class="payplug HostedFields_error -exp">
					<span class="-hide invalidField">{$f(esc_html__('payplug_integrated_payment_exp_error', 'payplug'))}</span>
					<span class="-hide emptyField">{$f(esc_html__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
				<div class="payplug HostedFields_error -cvv">
					<span class="-hide invalidField">{$f(esc_html__('payplug_integrated_payment_cvv_error', 'payplug'))}</span>
					<span class="-hide emptyField">{$f(esc_html__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>

				{$saved}

				<div class="payplug HostedFields_error -payment">
					<span class="-hide genericError">{$f(esc_html__('payplug_hosted_fields_tokenization_error', 'payplug'))}</span>
					<span class="-hide brandError">{$f(esc_html__('payplug_hosted_fields_unsupported_brand_error', 'payplug'))}</span>
				</div>

				<div class="payplug HostedFields_privacy_policy">
					<img class="-lock" src="{$lock}" alt="" />
					<span>{$f(esc_html__('payplug_integrated_payment_transaction_secure', 'payplug'))}</span>
					<img class="-logo" src="{$logo}" alt="Payplug" />
					<br />
					<a href="{$privacy_policy_url}" target="_blank" rel="noopener noreferrer">{$f(esc_html__('payplug_integrated_payment_privacy_policy', 'payplug'))}</a>
				</div>
			</div>
HTML;
    }
}
