<?php

namespace Payplug\PayplugWoocommerce\Front;

class HostedFields
{
    public function __construct()
    {
        add_action('wc_ajax_payplug_hosted_fields_token', [$this, 'receive_token']);
    }

    public function receive_token(): void
    {
        $hf_token = isset($_POST['hfToken']) ? sanitize_text_field(wp_unslash($_POST['hfToken'])) : '';

        if (empty($hf_token)) {
            wp_send_json_error([
                'message' => __('payplug_hosted_fields_missing_token', 'payplug'),
            ]);
        }

        $selected_brand = isset($_POST['selectedBrand']) ? sanitize_text_field(wp_unslash($_POST['selectedBrand'])) : '';
        $save_card = !empty($_POST['save_card']);

        wp_send_json_success([
            'hfToken' => $hf_token,
            'selectedBrand' => $selected_brand,
            'save_card' => $save_card,
        ]);
    }
}
