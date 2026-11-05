<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmAmountsSave;
use PHPUnit\Framework\TestCase;

class ApmAmountsSave_test extends TestCase
{
    private const API_MIN = 30;
    private const API_MAX = 2000000;

    /**
     * @dataProvider submissions_provider
     *
     * @param mixed $min
     * @param mixed $max
     */
    public function test_parse($min, $max, ?string $error, array $store): void
    {
        $result = ApmAmountsSave::parse($min, $max, self::API_MIN, self::API_MAX);

        self::assertFalse($result['skip']);
        self::assertSame($error, $result['error']);
        self::assertSame($store, $result['store']);
    }

    public function submissions_provider(): array
    {
        return [
            'zero euro' => [0, 20000, 'out_of_bounds', []],
            'ten cents below a 0.30 bound' => ['0.10', 20000, 'out_of_bounds', []],
            'fifty cents' => ['0.5', 20000, null, ['min' => 50]],
            'equal to the API min' => ['0.3', 20000, null, []],
            'equal to both API bounds' => [0.3, '20000', null, []],
            'max above the API max' => [1, '20001', 'out_of_bounds', []],
            'max one cent above the API max' => [1, '20000.01', 'out_of_bounds', []],
            'min above the API max' => ['30000', '', 'out_of_bounds', []],
            'max equal to min' => [10, 10, 'max_gt_min', []],
            'max below min' => [100, 10, 'max_gt_min', []],
            'max below the API min once min is cleared' => ['', '0.2', 'out_of_bounds', []],
            'cleared min' => ['', 100, null, ['max' => 10000]],
            'cleared both' => ['', '', null, []],
            'whitespace only' => ['  ', 100, null, ['max' => 10000]],
            'not a number' => ['abc', 100, 'invalid', []],
            'negative' => [-1, 100, 'invalid', []],
            'comma decimal separator' => ['0,5', 100, 'invalid', []],
            'array value' => [['1'], 100, 'invalid', []],
            'boolean value' => [true, 100, 'invalid', []],
            'rounded, not truncated' => ['49.99', 100, null, ['min' => 4999, 'max' => 10000]],
            'float precision' => [19.99, 1000.1, null, ['min' => 1999, 'max' => 100010]],
        ];
    }

    public function test_absent_keys_are_skipped(): void
    {
        self::assertTrue(ApmAmountsSave::parse(null, null, self::API_MIN, self::API_MAX)['skip']);
        self::assertTrue(ApmAmountsSave::parse(null, '100', self::API_MIN, self::API_MAX)['skip']);
        self::assertTrue(ApmAmountsSave::parse('1', null, self::API_MIN, self::API_MAX)['skip']);
    }

    /**
     * The BO posts an emptied amount field as null (NaN once JSON-encoded) and an empty initial value as false.
     *
     * @dataProvider posted_values_provider
     *
     * @param mixed $expected
     */
    public function test_posted_value($expected, array $data): void
    {
        self::assertSame($expected, ApmAmountsSave::posted($data, 'bancontact_min_amounts'));
    }

    public function posted_values_provider(): array
    {
        return [
            'absent key' => [null, ['bancontact_max_amounts' => 10]],
            'emptied field' => ['', ['bancontact_min_amounts' => null]],
            'empty initial value' => ['', ['bancontact_min_amounts' => false]],
            'number' => [0.3, ['bancontact_min_amounts' => 0.3]],
            'string' => ['12', ['bancontact_min_amounts' => '12']],
            'zero' => [0, ['bancontact_min_amounts' => 0]],
        ];
    }

    public function test_emptied_field_restores_the_api_bound(): void
    {
        $data = ['bancontact_min_amounts' => null, 'bancontact_max_amounts' => 100];

        $result = ApmAmountsSave::parse(
            ApmAmountsSave::posted($data, 'bancontact_min_amounts'),
            ApmAmountsSave::posted($data, 'bancontact_max_amounts'),
            self::API_MIN,
            self::API_MAX
        );

        self::assertFalse($result['skip']);
        self::assertSame(['max' => 10000], $result['store']);
    }

    /**
     * @dataProvider euro_amounts_provider
     */
    public function test_euros(int $cents, string $expected): void
    {
        self::assertSame($expected, ApmAmountsSave::euros($cents));
    }

    public function euro_amounts_provider(): array
    {
        return [[30, '0.30'], [500, '5'], [2000000, '20000'], [4999, '49.99'], [0, '0']];
    }
}
