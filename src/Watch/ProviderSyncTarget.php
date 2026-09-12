<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Providers\ShowProvider;
use Unolia\Cli\Console\ExitCode;

/**
 * A provider sync has no row of its own: it is done when the provider's
 * synced_at moves past what it was before the sync was queued.
 */
final class ProviderSyncTarget implements Target
{
    public function __construct(
        private readonly Client $api,
        private readonly string $id,
        private readonly mixed $previousSyncedAt,
    ) {}

    public function fetch(): TargetState
    {
        $data = $this->api->send(new ShowProvider($this->id))->json('data');

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : [];

        return new TargetState($data);
    }

    public function events(?TargetState $previous, TargetState $state): iterable
    {
        $payload = ['provider' => $state->int('id'), 'name' => $state->string('name'), 'sync_status' => $state->string('sync_status')];

        if ($this->isDone($state)) {
            yield new WatchEvent('sync.finished', $payload + ['has_sync_error' => $state->get('has_sync_error') === true], $this->summary($state));

            return;
        }

        if ($previous === null) {
            yield new WatchEvent('sync.started', $payload, sprintf('Syncing %s', (string) $state->string('name', 'the provider')));
        }
    }

    public function isDone(TargetState $state): bool
    {
        return $state->get('synced_at') !== $this->previousSyncedAt;
    }

    public function exitCode(TargetState $state): ExitCode
    {
        return $state->get('has_sync_error') === true ? ExitCode::RemoteFailure : ExitCode::Ok;
    }

    public function summary(TargetState $state): string
    {
        $name = (string) $state->string('name', 'the provider');

        return $state->get('has_sync_error') === true
            ? sprintf('Sync of %s failed; unolia provider view %s says more', $name, $this->id)
            : sprintf('Synced %s', $name);
    }

    public function header(TargetState $state): ?string
    {
        return null;
    }
}
