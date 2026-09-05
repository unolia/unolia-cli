<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

/**
 * Turns the paths the agent catalog declares into absolute ones: `~` is the home
 * directory (`~/.config` honors XDG_CONFIG_HOME), `%VAR%` expands on Windows, and a
 * relative path resolves against the directory it was declared for.
 */
final class ConfigPaths
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(private readonly array $env) {}

    public function expand(string $path, ?string $basePath = null): string
    {
        if (str_starts_with($path, '~/.config/')) {
            $xdg = $this->env('XDG_CONFIG_HOME');

            if ($xdg !== null) {
                return rtrim($xdg, '/').'/'.substr($path, strlen('~/.config/'));
            }
        }

        if (str_starts_with($path, '~')) {
            return $this->home().substr($path, 1);
        }

        $path = (string) preg_replace_callback(
            '/%([^%]+)%/',
            fn (array $matches): string => $this->env($matches[1]) ?? $matches[0],
            $path,
        );

        if ($basePath !== null && ! str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            return rtrim($basePath, '/').'/'.$path;
        }

        return $path;
    }

    public function home(): string
    {
        return rtrim($this->env('HOME') ?? $this->env('USERPROFILE') ?? '', '/');
    }

    /** The path as a person reads it: relative inside the working directory, `~` inside home. */
    public function display(string $path, string $cwd): string
    {
        $cwd = rtrim($cwd, '/');

        if (str_starts_with($path, $cwd.'/')) {
            return substr($path, strlen($cwd) + 1);
        }

        $home = $this->home();

        return $home !== '' && str_starts_with($path, $home) ? '~'.substr($path, strlen($home)) : $path;
    }

    private function env(string $key): ?string
    {
        $value = $this->env[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
