<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front\Scalapay;

use PHPUnit\Framework\TestCase;

/**
 * The Scalapay widget is credit advertising: only the v5 script is loaded, and the plugin
 * neither styles nor wraps it.
 */
class ScalapayWidgetGuard_test extends TestCase
{
    private const V5_URL = '#^(?:https?:)?//cdn\.scalapay\.com/widget/v5/#';

    private const SCANNED = ['src', 'resources', 'assets/js', 'payplug.php', 'payplug-config.php'];

    public function test_no_scalapay_script_other_than_v5(): void
    {
        $others = [];
        foreach ($this->files(self::SCANNED) as $file) {
            preg_match_all('#(?:https?:)?//[a-z0-9.-]*scalapay\.com[^\s\'"`)]*#i', (string) file_get_contents($file), $matches);
            foreach (preg_grep(self::V5_URL, $matches[0], PREG_GREP_INVERT) as $url) {
                $others[$url][] = $file;
            }
        }

        self::assertSame([], $others);
    }

    public function test_the_widget_script_is_enqueued_only_from_the_build_time_url(): void
    {
        $sources = [];
        $references = 0;
        foreach ($this->files(['src']) as $file) {
            $code = (string) file_get_contents($file);
            preg_match_all('/wp_(?:enqueue|register)_script\(\s*(?:self|ScalapayWidgetDisplay)::SCRIPT_HANDLE\s*,\s*([^,]+?)\s*,/', $code, $matches);
            $sources = array_merge($sources, $matches[1]);
            $references += preg_match_all('/\bSCALAPAY_WIDGET_URL\b/', $code);
        }

        self::assertSame(['SCALAPAY_WIDGET_URL'], $sources);
        self::assertSame(1, $references);
    }

    public function test_the_build_time_url_is_empty_or_v5(): void
    {
        self::assertTrue('' === SCALAPAY_WIDGET_URL || 1 === preg_match(self::V5_URL, SCALAPAY_WIDGET_URL), SCALAPAY_WIDGET_URL);
    }

    public function test_no_legacy_widget_loader(): void
    {
        foreach ($this->files(self::SCANNED) as $file) {
            self::assertNotRegExp('/scalapay-widget-loader|widget\/v[0-4]\//i', (string) file_get_contents($file), $file);
        }
    }

    public function test_no_stylesheet_targets_the_widget(): void
    {
        foreach ($this->files(['assets/css', 'assets/scss']) as $file) {
            self::assertStringNotContainsStringIgnoringCase('scalapay', (string) file_get_contents($file), $file);
        }
    }

    /**
     * @param string[] $paths relative to the plugin root
     *
     * @return string[]
     */
    private function files(array $paths): array
    {
        $root = dirname(__DIR__, 5);
        $files = [];
        foreach ($paths as $path) {
            if (is_file($root . '/' . $path)) {
                $files[] = $root . '/' . $path;
                continue;
            }
            if (!is_dir($root . '/' . $path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (preg_match('/\.(php|js|jsx|ts|css|scss|html)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
