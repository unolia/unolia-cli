<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Unolia\UnoliaCLI\Helpers\Helpers;

class UnoliaAuth extends Connector
{
    use AcceptsJson;

    public function __construct(
        public readonly ?string $token = null,
        public readonly ?string $api_url = null,
    ) {}

    protected function defaultAuth(): ?TokenAuthenticator
    {
        return $this->token ? new TokenAuthenticator($this->token) : null;
    }

    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => Helpers::getUserAgent(),
        ];
    }

    public function resolveBaseUrl(): string
    {
        return $this->api_url ?? 'https://app.unolia.com/api/';
    }
}
