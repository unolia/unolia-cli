<?php

declare(strict_types=1);

namespace Unolia\Cli\Config;

use Unolia\Cli\Console\CliError;

/**
 * Where the CLI keeps its files. XDG aware, and it knows where v1 left its token.
 */
final class Paths
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(private readonly array $env) {}

    public function home(): string
    {
        $home = $this->env['HOME'] ?? $this->env['USERPROFILE'] ?? null;

        if ($home === null || $home === '') {
            throw CliError::usage('HOME is not set, so the CLI cannot find its configuration directory.');
        }

        return rtrim($home, '/');
    }

    public function configDir(): string
    {
        $xdg = $this->env['XDG_CONFIG_HOME'] ?? null;

        if (is_string($xdg) && $xdg !== '') {
            return rtrim($xdg, '/').'/unolia';
        }

        return $this->home().'/.config/unolia';
    }

    public function hostsFile(): string
    {
        return $this->configDir().'/hosts.json';
    }

    public function settingsFile(): string
    {
        return $this->configDir().'/config.json';
    }

    public function cacheFile(): string
    {
        return $this->configDir().'/cache.json';
    }

    public function legacyConfigFile(): string
    {
        return $this->home().'/.unolia/cli/config.json';
    }

    public function ensureConfigDir(): string
    {
        $dir = $this->configDir();

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw CliError::usage(sprintf('could not create %s', $dir));
        }

        return $dir;
    }

    /**
     * @return array<mixed>
     */
    public function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw CliError::usage(sprintf('%s is not valid JSON', $path), 'Fix the file or delete it.');
        }

        return $decoded;
    }

    /**
     * @param  array<mixed>  $data
     */
    public function writeJson(string $path, array $data, int $mode): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw CliError::usage(sprintf('could not encode %s', $path));
        }

        $this->writeAtomic($path, $json."\n", $mode);
    }

    public function writeAtomic(string $path, string $contents, int $mode = 0644): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw CliError::usage(sprintf('could not create %s', $dir));
        }

        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($temp, $contents) === false) {
            throw CliError::usage(sprintf('could not write %s', $path));
        }

        @chmod($temp, $mode);

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw CliError::usage(sprintf('could not write %s', $path));
        }

        @chmod($path, $mode);
    }
}
