<?php

/*
 * The test process has already printed output, so headers_sent() is true and wp_send_json()
 * never calls status_header(): the HTTP status is only observable at the call site. These
 * functions shadow the WordPress ones for the plugin namespaces (unqualified calls resolve to
 * the namespaced function first), record the status, then delegate to WordPress unchanged.
 */

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support {
    final class JsonStatusSpy
    {
        /**
         * @var int|null
         */
        public static $status;

        /**
         * @var int
         */
        public static $calls = 0;

        public static function reset(): void
        {
            self::$status = null;
            self::$calls = 0;
        }

        /**
         * @param int|null $status_code
         */
        public static function record($status_code): void
        {
            self::$status = null === $status_code ? 200 : (int) $status_code;
            self::$calls++;
        }
    }

    /**
     * Stands for the exit of wp_die(). An \Error, so that catch (\Exception) blocks around
     * wp_send_json() in the code under test do not swallow it.
     */
    final class AjaxDieException extends \Error
    {
    }
}

namespace Payplug\PayplugWoocommerce {
    use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\JsonStatusSpy;

    function wp_send_json($response, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json($response, $status_code, $flags);
    }

    function wp_send_json_success($value = null, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json_success($value, $status_code, $flags);
    }

    function wp_send_json_error($value = null, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json_error($value, $status_code, $flags);
    }
}

namespace Payplug\PayplugWoocommerce\Front {
    use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\JsonStatusSpy;

    function wp_send_json($response, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json($response, $status_code, $flags);
    }

    function wp_send_json_success($value = null, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json_success($value, $status_code, $flags);
    }

    function wp_send_json_error($value = null, $status_code = null, $flags = 0): void
    {
        JsonStatusSpy::record($status_code);
        \wp_send_json_error($value, $status_code, $flags);
    }
}
