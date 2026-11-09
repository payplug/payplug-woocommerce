<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

use PayplugUnifiedCore\Contracts\IUnifiedApiHttpClient;

/**
 * HTTP client answering get() from a queue of responses (or throwables to throw) and recording each call.
 */
final class ScriptedHttpClient implements IUnifiedApiHttpClient
{
    /**
     * @var array[] url and headers of each get() call
     */
    public $calls = [];

    /**
     * @var array<array{status:int, body:string}|\Throwable>
     */
    private $responses;

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function get(string $url, array $headers = []): array
    {
        $this->calls[] = [$url, $headers];
        if ([] === $this->responses) {
            throw new \LogicException('No scripted response left for ' . $url);
        }
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    public function postJson(string $url, array $body, array $headers = []): array
    {
        throw new \LogicException('postJson() is not expected here');
    }
}
