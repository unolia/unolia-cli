<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class Logout extends Request
{
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return 'logout';
    }
}
