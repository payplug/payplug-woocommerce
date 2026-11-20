<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front\Scalapay;

use PHPUnit\Framework\TestCase;

/**
 * The Scalapay widget is credit advertising: only the v5 script is loaded, and the plugin
 * neither styles nor wraps it.
 */
class ScalapayWidgetGuard_test extends TestCase
{
    private const WIDGET_URL = 'https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js';

    private const SCANNED = ['src', 'resources', 'assets/js', 'payplug.php', 'payplug-config.php'];

    public function test_the_v5_script_is_the_only_scalapay_script(): void
    {
        $urls = [];
        foreach ($this->files(self::SCANNED) as $file) {
            preg_match_all('#(?:https?:)?//[a-z0-9.-]*scalapay\.com[^\s\'"`)]*#i', (string) file_get_contents($file), $matches);
            foreach ($matches[0] as $url) {
                $urls[$url][] = $file;
            }
        }

        self::assertSame([self::WIDGET_URL], array_keys($urls));
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
