<?php

namespace Unolia\UnoliaCLI\Helpers;

use Unolia\UnoliaCLI\Http\Integrations\Unolia\Unolia;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\UnoliaAuth;

class Helpers
{
    public static function getApiConnector($token = null): Unolia
    {
        return new Unolia(
            token: $token ?? self::getApiToken(),
            api_url: config('settings.api.url'),
        );
    }

    public static function getAuthConnector($token = null): UnoliaAuth
    {
        return new UnoliaAuth(
            token: $token ?? self::getApiToken(),
            api_url: config('settings.api.auth_url'),
        );
    }

    public static function getApiToken(): ?string
    {
        // Check environment variable, else use config file
        return config('settings.api.token') ?: Config::get('api.token');
    }

    public static function getUserAgent()
    {
        $phpVersion = 'PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION;

        return sprintf(
            'User-Agent: UnoliaCLI/%s (%s; %s; %s; %s)',
            config('app.version'),
            function_exists('php_uname') ? php_uname('s') : 'Unknown',
            function_exists('php_uname') ? php_uname('m') : 'Unknown',
            function_exists('php_uname') ? php_uname('r') : 'Unknown',
            $phpVersion,
        );
    }
}
