<?php

declare(strict_types=1);

namespace Unolia\Cli\Config;

use Unolia\Cli\Support\Arr;

/**
 * Tokens per host in hosts.json, mode 0600. Nothing here is ever printed.
 */
final class Hosts
{
    public const DEFAULT_HOST = 'app.unolia.com';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $entries = null;

    /** @var list<string> */
    private array $notices = [];

    private bool $migrated = false;

    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        private readonly Paths $paths,
        private readonly array $env,
    ) {}

    /**
     * The token used for a host: the environment first, then the file.
     */
    public function tokenFor(string $host): ?string
    {
        $fromEnv = $this->env['UNOLIA_TOKEN'] ?? null;

        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $deprecated = $this->env['UNOLIA_API_TOKEN'] ?? null;

        if (is_string($deprecated) && $deprecated !== '') {
            $this->notices[] = 'UNOLIA_API_TOKEN is deprecated. Use UNOLIA_TOKEN.';

            return $deprecated;
        }

        $entry = $this->entry($host);
        $token = $entry['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** Where the token in use comes from, for `unolia status`. */
    public function tokenSource(string $host): ?string
    {
        if (is_string($this->env['UNOLIA_TOKEN'] ?? null) && $this->env['UNOLIA_TOKEN'] !== '') {
            return 'UNOLIA_TOKEN';
        }

        if (is_string($this->env['UNOLIA_API_TOKEN'] ?? null) && $this->env['UNOLIA_API_TOKEN'] !== '') {
            return 'UNOLIA_API_TOKEN';
        }

        return $this->entry($host) === [] ? null : 'hosts.json';
    }

    /**
     * @return array<string, mixed>
     */
    public function entry(string $host): array
    {
        $entry = $this->all()[$host] ?? [];

        return is_array($entry) ? $entry : [];
    }

    public function put(string $host, string $token, string $kind = 'unknown', ?string $name = null): void
    {
        $entries = $this->all();

        $entries[$host] = Arr::filled([
            'token' => $token,
            'kind' => $kind,
            'name' => $name,
            'created_at' => gmdate('c'),
        ]);

        $this->save($entries);
    }

    public function forget(string $host): void
    {
        $entries = $this->all();
        unset($entries[$host]);

        $this->save($entries);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->entries === null) {
            /** @var array<string, array<string, mixed>> $entries */
            $entries = $this->paths->readJson($this->paths->hostsFile());
            $this->entries = $entries;
            $this->migrateLegacyToken();
        }

        return $this->entries;
    }

    /**
     * Take the pending one time notices, so the caller prints each of them once.
     *
     * @return list<string>
     */
    public function takeNotices(): array
    {
        $notices = $this->notices;
        $this->notices = [];

        return $notices;
    }

    /**
     * v1 kept the token in ~/.unolia/cli/config.json. Copy it once, never delete it.
     */
    private function migrateLegacyToken(): void
    {
        if ($this->migrated || isset($this->entries[self::DEFAULT_HOST])) {
            return;
        }

        $this->migrated = true;
        $legacy = $this->paths->legacyConfigFile();

        if (! is_file($legacy)) {
            return;
        }

        $token = Arr::get($this->paths->readJson($legacy), 'api.token');

        if (! is_string($token) || $token === '') {
            return;
        }

        $entries = $this->entries ?? [];
        $entries[self::DEFAULT_HOST] = [
            'token' => $token,
            'kind' => 'unknown',
            'name' => null,
            'created_at' => gmdate('c'),
        ];

        $this->entries = $entries;
        $this->save($entries);

        $this->notices[] = 'Migrated your token from ~/.unolia/cli/config.json';
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     */
    private function save(array $entries): void
    {
        $this->entries = $entries;
        $this->paths->ensureConfigDir();
        $this->paths->writeJson($this->paths->hostsFile(), $entries, 0600);
    }
}
