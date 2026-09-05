<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Unolia\Cli\Runtime;

/**
 * Builds connectors. Commands that must talk to the API with another token, such as
 * `login` verifying what you just pasted, go through here so tests can still fake it.
 */
class ClientFactory
{
    public function __construct(protected readonly Runtime $runtime) {}

    public function make(string $host, ?string $token, string $kind = 'unknown', string $basePath = 'api/v1/'): Client
    {
        $client = new Client(
            host: $host,
            token: $token,
            tokenKind: $kind,
            insecure: $this->insecure($host),
            basePath: $basePath,
        );

        $runtime = $this->runtime;
        $client->resolveTeamUsing(static fn (): ?string => $runtime->context()->teamSlug());

        if ($runtime->flag('UNOLIA_DEBUG')) {
            $client->logDebugUsing(static function (string $line) use ($runtime): void {
                $runtime->out()->warn($line);
            });
        }

        return $client;
    }

    /** A connector rooted at the host itself, for `unolia api` which addresses any path. */
    public function raw(string $host, ?string $token, string $kind = 'unknown'): Client
    {
        return $this->make($host, $token, $kind, '');
    }

    public function auth(string $host, ?string $token = null): AuthClient
    {
        return new AuthClient(
            host: $host,
            token: $token,
            insecure: $this->insecure($host),
        );
    }

    protected function insecure(string $host): bool
    {
        return $this->runtime->flag('UNOLIA_INSECURE')
            || str_ends_with($host, '.test')
            || str_starts_with($host, 'localhost');
    }
}
