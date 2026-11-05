<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use Payplug\PayplugWoocommerce\Service\Configuration;
use PHPUnit\Framework\TestCase;

class Configuration_test extends TestCase
{
    public function test_every_restricted_apm_has_merchant_restriction_fields(): void
    {
        $defaults = $this->configuration()->extract_options_from_fields();

        foreach (ApmEligibility::APMS as $apm) {
            $configuration = $defaults['payment_methods']['configuration'][$apm];
            self::assertSame('{}', $configuration['custom_amounts'], $apm);
            self::assertSame('', $configuration['custom_countries'], $apm);
        }
    }

    public function test_scalapay_has_no_default_amounts_and_oney_keeps_its_own(): void
    {
        $defaults = $this->configuration()->extract_options_from_fields();

        self::assertArrayNotHasKey('default_amounts', $defaults['payment_methods']['configuration']['scalapay']);
        self::assertSame('{"min":10000, "max":300000}', $defaults['payment_methods']['configuration']['oney']['default_amounts']);
    }

    private function configuration(): Configuration
    {
        return (new \ReflectionClass(Configuration::class))->newInstanceWithoutConstructor();
    }
}
