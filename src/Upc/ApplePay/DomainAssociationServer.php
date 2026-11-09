<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * Answers Apple's request for the domain-association file from the stored copy, before any other
 * plugin can redirect, restrict or cache the request. A file placed by hand at the web root wins.
 *
 * The route only answers when the PayPlug account has Apple Pay, so the file of another payment
 * provider is left alone. The plugin's Apple Pay setting cannot be the gate: it can only be
 * enabled once the account lists the domain, which requires Apple to have verified this file.
 */
final class DomainAssociationServer
{
    public const PATH = '/.well-known/apple-developer-merchantid-domain-association';

    /**
     * @var callable(): DomainAssociationService
     */
    private $service_factory;

    /**
     * @var callable(): bool
     */
    private $account_allows_apple_pay;

    /**
     * @var string|null
     */
    private $document_root;

    public function __construct(callable $service_factory, callable $account_allows_apple_pay, ?string $document_root = null)
    {
        $this->service_factory = $service_factory;
        $this->account_allows_apple_pay = $account_allows_apple_pay;
        $this->document_root = $document_root;
    }

    public function maybe_serve(): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        $response = $this->respond($uri, $method);
        if (null !== $response) {
            $this->emit($response);
        }
    }

    public static function matches(string $uri): bool
    {
        $path = wp_parse_url($uri, PHP_URL_PATH);

        return is_string($path) && untrailingslashit($path) === self::PATH;
    }

    public static function root_file_present(?string $document_root = null): bool
    {
        if (null === $document_root) {
            $document_root = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        }

        return '' !== $document_root && is_file(rtrim($document_root, '/\\') . self::PATH);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string, send_body: bool}|null null when the request is left to WordPress
     */
    public function respond(string $uri, string $method): ?array
    {
        if (!self::matches($uri)
            || !in_array($method, ['GET', 'HEAD'], true)
            || !call_user_func($this->account_allows_apple_pay)
            || self::root_file_present($this->document_root)) {
            return null;
        }

        $content = call_user_func($this->service_factory)->resolve();
        if (null === $content) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store'],
                'body' => '',
                'send_body' => false,
            ];
        }

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/plain',
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => $content,
            'send_body' => 'HEAD' !== $method,
        ];
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string, send_body: bool} $response
     */
    protected function emit(array $response): void
    {
        if (404 === $response['status'] && !defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        status_header($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($response['send_body']) {
            echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file signed for Apple, served byte for byte
        }
        exit;
    }
}
