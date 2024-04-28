<?php

namespace App;

use App\Http\Integrations\Unolia\Unolia;

class Helpers
{
    public static function connector()
    {
        return new Unolia(
            token: config('settings.api.token'),
            api_url: config('settings.api.url'),
        );
    }
}
