<?php

namespace App\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CurrentToken extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'current/token';
    }
}
