<?php

namespace App\Http\Integrations\Unolia;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class Unolia extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://app.unolia.com/api/v1/';
    }
}
