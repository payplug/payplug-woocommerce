<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use PHPUnit\Framework\TestCase;

/**
 * Static guard, not a behavior test: the card field of the hosted fields configuration must enable
 * autospacing, which the brand choice of co-badged cards needs to work (PRE-3875). This reads the
 * scripts and the built bundle; the brand switch itself can only be covered by a Playwright case.
 */
class HostedFieldsBrandSwitch_test extends TestCase
{
    private const CARD_FIELD_START = '/card:\s*\{\s*id:\s*[\'"]hosted-fields-card[\'"]/';

    public function testClassicCardFieldEnablesAutospacing(): void
    {
        $this->assertCardFieldEnablesAutospacing(
            'assets/js/payplug-hosted-fields.js',
            '/enableAutospacing:\s*true\b/'
        );
    }

    public function testBlocksSourceCardFieldEnablesAutospacing(): void
    {
        $this->assertCardFieldEnablesAutospacing(
            'resources/js/frontend/wc-payplug-hostedFields-blocks.js',
            '/enableAutospacing:\s*true\b/'
        );
    }

    public function testBuiltBlocksBundleCardFieldEnablesAutospacing(): void
    {
        $this->assertCardFieldEnablesAutospacing(
            'assets/js/blocks/wc-payplug-payplug-blocks.js',
            '/enableAutospacing:!0\b/',
            ', run npm run build'
        );
    }

    private function assertCardFieldEnablesAutospacing(string $path, string $option, string $hint = ''): void
    {
        $this->assertSame(
            1,
            preg_match($option, $this->extractCardField($path)),
            $path . ': the card field does not enable autospacing' . $hint
        );
    }

    /**
     * Returns the card object from its opening brace to the matching closing one. Braces inside
     * string literals or comments are counted as well.
     */
    private function extractCardField(string $path): string
    {
        $contents = $this->read($path);
        $this->assertSame(
            1,
            preg_match(self::CARD_FIELD_START, $contents, $match, PREG_OFFSET_CAPTURE),
            $path . ': no card field with id hosted-fields-card'
        );

        $start = strpos($contents, '{', $match[0][1]);
        $depth = 0;
        $length = strlen($contents);
        for ($end = $start; $end < $length; $end++) {
            if ($contents[$end] === '{') {
                $depth++;
            } elseif ($contents[$end] === '}' && --$depth === 0) {
                break;
            }
        }
        $this->assertSame(0, $depth, $path . ': the card field is not closed');

        return substr($contents, $start, $end - $start + 1);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/' . $path);
        $this->assertIsString($contents, $path . ' could not be read');

        return $contents;
    }
}
