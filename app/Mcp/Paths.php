<?php

namespace Unolia\UnoliaCLI\Mcp;

class Paths
{
    /**
     * Expand a config path to an absolute one: `~` becomes the user's home
     * (`~/.config` honors $XDG_CONFIG_HOME), `%VAR%` expands from the
     * environment on Windows, and relative paths resolve against $basePath.
     */
    public static function expand(string $path, ?string $basePath = null): string
    {
        if (str_starts_with($path, '~/.config/')) {
            $xdg = self::env('XDG_CONFIG_HOME');

            if ($xdg) {
                return rtrim($xdg, '/').'/'.substr($path, strlen('~/.config/'));
            }
        }

        if (str_starts_with($path, '~')) {
            return self::home().substr($path, 1);
        }

        $path = (string) preg_replace_callback(
            '/%([^%]+)%/',
            fn (array $matches) => self::env($matches[1]) ?? $matches[0],
            $path,
        );

        if ($basePath !== null && ! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return rtrim($basePath, '/').'/'.$path;
        }

        return $path;
    }

    public static function home(): string
    {
        return $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'];
    }

    private static function env(string $key): ?string
    {
        $value = $_SERVER[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
