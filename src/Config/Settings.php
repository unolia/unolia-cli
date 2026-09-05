<?php

declare(strict_types=1);

namespace Unolia\Cli\Config;

use Unolia\Cli\Console\CliError;

/**
 * ~/.config/unolia/config.json, the handful of options `unolia config` exposes.
 */
final class Settings
{
    public const KEYS = ['default_team', 'format', 'editor', 'color', 'browser', 'host'];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        private readonly Paths $paths,
        private readonly array $env,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKnown($key);

        return $this->all()[$key] ?? $this->fallback($key) ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $this->assertKnown($key);

        $values = $this->all();

        if ($value === null || $value === '') {
            unset($values[$key]);
        } else {
            $values[$key] = $value;
        }

        $this->values = $values;
        $this->paths->ensureConfigDir();
        $this->paths->writeJson($this->paths->settingsFile(), $values, 0644);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            /** @var array<string, mixed> $values */
            $values = $this->paths->readJson($this->paths->settingsFile());
            $this->values = $values;
        }

        return $this->values;
    }

    /**
     * Every known key with its effective value, for `unolia config list`.
     *
     * @return array<string, mixed>
     */
    public function effective(): array
    {
        $effective = [];

        foreach (self::KEYS as $key) {
            $effective[$key] = $this->get($key);
        }

        return $effective;
    }

    /** The host every request goes to. UNOLIA_HOST wins over the file. */
    public function host(): string
    {
        $fromEnv = $this->env['UNOLIA_HOST'] ?? null;

        if (is_string($fromEnv) && $fromEnv !== '') {
            return $this->normalizeHost($fromEnv);
        }

        $stored = $this->all()['host'] ?? null;

        return is_string($stored) && $stored !== '' ? $this->normalizeHost($stored) : Hosts::DEFAULT_HOST;
    }

    public function defaultTeam(): ?string
    {
        $team = $this->all()['default_team'] ?? null;

        return is_string($team) && $team !== '' ? $team : null;
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);
        $host = (string) preg_replace('#^https?://#', '', $host);

        return rtrim($host, '/');
    }

    private function fallback(string $key): mixed
    {
        return match ($key) {
            'format' => 'table',
            'color' => 'auto',
            'editor' => $this->env['EDITOR'] ?? null,
            'host' => $this->host(),
            default => null,
        };
    }

    private function assertKnown(string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            throw CliError::usage(
                sprintf('unknown config key %s', $key),
                'Known keys: '.implode(', ', self::KEYS),
                ['candidates' => self::KEYS],
            );
        }
    }
}
