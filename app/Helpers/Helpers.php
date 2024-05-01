<?php

namespace Unolia\UnoliaCLI\Helpers;

use Unolia\UnoliaCLI\Http\Integrations\Unolia\Unolia;

class Helpers
{
    public static function getApiConnector($token = null): Unolia
    {
        return new Unolia(
            token: $token ?: self::getApiToken(),
            api_url: config('settings.api.url'),
        );
    }

    public static function getApiToken(): ?string
    {
        // Check environment variable, else use config file
        return config('settings.api.token') ?: Config::get('api.token');
    }
}
