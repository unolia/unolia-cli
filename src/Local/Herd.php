<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * Laravel Herd on this machine. Every method degrades to null or false when Herd is absent.
 */
class Herd extends Tool
{
    public function __construct(protected readonly string $cwd) {}

    public function isInstalled(): bool
    {
        return $this->herd(['--version'])->successful();
    }

    public function version(): ?string
    {
        $result = $this->herd(['--version']);

        if (! $result->successful()) {
            return null;
        }

        return preg_match('/(\d+\.\d+(\.\d+)?)/', $result->trimmed(), $matches) === 1 ? $matches[1] : $result->trimmed();
    }

    /**
     * The PHP versions Herd has installed.
     *
     * @return list<string>
     */
    public function phpVersions(): array
    {
        $result = $this->herd(['php:list']);

        if (! $result->successful()) {
            return [];
        }

        $versions = [];

        foreach (explode("\n", $result->output) as $line) {
            if (stripos($line, 'installed') === false) {
                continue;
            }

            if (preg_match('/(\d+\.\d+)/', $line, $matches) === 1) {
                $versions[$matches[1]] = true;
            }
        }

        return array_keys($versions);
    }

    /** The PHP version isolated for a directory, read from `herd isolated`. */
    public function isolatedVersion(string $directory): ?string
    {
        $result = $this->herd(['isolated']);

        if (! $result->successful()) {
            return null;
        }

        $needle = rtrim($directory, '/');

        foreach (explode("\n", $result->output) as $line) {
            if (! str_contains($line, $needle)) {
                continue;
            }

            if (preg_match('/(\d+\.\d+)/', str_replace($needle, '', $line), $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @return list<string> */
    public function securedSites(): array
    {
        $result = $this->herd(['secured']);

        if (! $result->successful()) {
            return [];
        }

        $sites = [];

        foreach (explode("\n", $result->output) as $line) {
            $line = trim($line, " \t|-");

            if ($line !== '' && ! str_contains(strtolower($line), 'site')) {
                $sites[] = explode(' ', $line)[0];
            }
        }

        return $sites;
    }

    public function siteName(string $directory): string
    {
        return basename(rtrim($directory, '/'));
    }

    /** Herd Pro manages services, which is what makes the services block meaningful. */
    public function isPro(): bool
    {
        return $this->herd(['services:list'])->successful();
    }

    /**
     * @param  (callable(string $line, bool $isError): void)|null  $onLine  relay herd's output as it happens
     */
    public function init(string $directory, ?callable $onLine = null): ProcessResult
    {
        return $this->herd(['init', '-n'], $directory, $onLine);
    }

    /**
     * @param  (callable(string $line, bool $isError): void)|null  $onLine
     */
    public function isolate(string $directory, string $php, ?callable $onLine = null): ProcessResult
    {
        return $this->herd(['isolate', $php], $directory, $onLine);
    }

    /**
     * @param  (callable(string $line, bool $isError): void)|null  $onLine
     */
    public function link(string $directory, ?callable $onLine = null): ProcessResult
    {
        return $this->herd(['link'], $directory, $onLine);
    }

    /**
     * @param  (callable(string $line, bool $isError): void)|null  $onLine
     */
    public function secure(string $site, ?callable $onLine = null): ProcessResult
    {
        return $this->herd(['secure', $site], null, $onLine);
    }

    /**
     * @param  list<string>  $arguments
     * @param  (callable(string $line, bool $isError): void)|null  $onLine  when given, the step runs without a time cap and streams
     */
    protected function herd(array $arguments, ?string $cwd = null, ?callable $onLine = null): ProcessResult
    {
        return $onLine === null
            ? $this->run('herd', $arguments, $cwd ?? $this->cwd)
            : $this->run('herd', $arguments, $cwd ?? $this->cwd, $onLine, null);
    }
}
