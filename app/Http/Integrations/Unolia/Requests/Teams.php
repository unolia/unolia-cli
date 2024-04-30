<?php

namespace App\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class Teams extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'teams';
    }
}
