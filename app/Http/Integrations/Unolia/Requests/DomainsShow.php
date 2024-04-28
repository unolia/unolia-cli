<?php

namespace App\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DomainsShow extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $domain
    ) {
    }

    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->domain;
    }
}
