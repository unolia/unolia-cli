<?php

namespace Unolia\UnoliaCLI\Helpers;

use Illuminate\Support\Arr;

class Config
{
    protected static ?array $configs = null;

    public static function get($key, $default = null): mixed
    {
        return Arr::get(static::all(), $key, $default);
    }

    public static function set($key, $value): void
    {
        Arr::set(static::$configs, $key, $value);

        file_put_contents(static::getConfigPath(), json_encode(static::$configs, JSON_PRETTY_PRINT));
    }

    public static function all(): array
    {
        if (is_null(static::$configs)) {
            $configPath = static::getConfigPath();

            if (! is_dir(dirname($configPath))) {
                mkdir(dirname($configPath), 0755, true);
            }

            if (file_exists($configPath)) {
                static::$configs = json_decode(file_get_contents($configPath), true);
            }
        }

        return static::$configs ?? [];
    }

    public static function getConfigPath(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'];

        return $home.'/.unolia/cli/config.json';
    }
}
