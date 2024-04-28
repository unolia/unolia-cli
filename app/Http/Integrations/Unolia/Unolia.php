<?php

namespace App\Http\Integrations\Unolia;

use App\Http\Integrations\Unolia\Paginator\UnoliaPaginator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\Traits\Plugins\AcceptsJson;

class Unolia extends Connector implements HasPagination
{
    use AcceptsJson;

    public function __construct(public readonly string $token)
    {
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->token);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.unolia.com/v1/';
    }

    public function paginate(Request $request): UnoliaPaginator
    {
        return new UnoliaPaginator(connector: $this, request: $request);
    }
}
