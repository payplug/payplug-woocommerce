<?php

namespace Payplug\PayplugWoocommerce\Service;

class ApmRefreshPolicy
{
    /**
     * Whether a BO save must refresh the cached API limits first: a restricted payment method has
     * no usable limits, or the limits were not fetched for the mode being saved.
     *
     * @param array $options woocommerce_payplug_settings, read raw
     * @param string $target_mode live|test, the mode being saved
     */
    public static function needs_refresh_on_save(array $options, string $target_mode): bool
    {
        if (($options['apm_cache_mode'] ?? '') !== $target_mode) {
            return true;
        }

        foreach (ApmEligibility::APMS as $apm) {
            if (null === ApmSettings::config_for($options, $apm)['limits']) {
                return true;
            }
        }

        return false;
    }
}
