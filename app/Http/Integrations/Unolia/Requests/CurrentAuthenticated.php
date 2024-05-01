<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CurrentAuthenticated extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'current/authenticated';
    }
}
