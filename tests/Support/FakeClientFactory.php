<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Api\AuthClient;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\ClientFactory;
use Unolia\Cli\Api\OAuthClient;
use Unolia\Cli\Runtime;

/**
 * Every connector the runtime hands out, wired to the canned answers.
 */
final class FakeClientFactory extends ClientFactory
{
    public function __construct(Runtime $runtime, private readonly FakeApi $api)
    {
        parent::__construct($runtime);
    }

    public function make(string $host, ?string $token, string $kind = 'unknown', string $basePath = 'api/v2/'): Client
    {
        return parent::make($host, $token, $kind, $basePath)->withMockClient($this->api->mockClient());
    }

    public function auth(string $host, ?string $token = null): AuthClient
    {
        return parent::auth($host, $token)->withMockClient($this->api->mockClient());
    }

    public function oauth(string $host): OAuthClient
    {
        return parent::oauth($host)->withMockClient($this->api->mockClient());
    }
}
