<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Exception;

use Payplug\PayplugWoocommerce\Exception\LockUnavailableException;
use PHPUnit\Framework\TestCase;

class LockUnavailableException_test extends TestCase
{
    public function test_is_a_runtime_exception_carrying_its_message(): void
    {
        $e = new LockUnavailableException('db down');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('db down', $e->getMessage());
    }
}
