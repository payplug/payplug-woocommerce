<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

/**
 * Plugin options of a live account where Scalapay is active, accepted in FR and IT from 5 to
 * 2000 EUR, with a merchant token for its widget.
 */
class ScalapayWidgetFixtures
{
    public const TOKEN = 'tok_test_123';

    /**
     * @param array $configuration merged into payment_methods.configuration.scalapay
     */
    public static function options(array $configuration = [], string $token = self::TOKEN): array
    {
        return [
            'enabled' => true,
            'mode' => true,
            'payment_methods' => [
                'permissions' => [
                    'scalapay' => [
                        'enabled' => true,
                        'countries' => '["FR","IT"]',
                        'amounts' => '{"min":{"EUR":500},"max":{"EUR":200000}}',
                        'merchant_token' => $token,
                    ],
                ],
                'configuration' => [
                    'scalapay' => array_merge(['active' => true], $configuration),
                ],
            ],
        ];
    }
}
