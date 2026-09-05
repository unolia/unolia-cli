<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Domains\ShowRecord;
use Unolia\Cli\Console\ExitCode;

/**
 * A DNS record, watched until the zone reports it verified.
 */
final class RecordTarget implements Target
{
    public function __construct(
        private readonly Client $api,
        private readonly int $id,
        private readonly bool $once = false,
    ) {}

    public function fetch(): TargetState
    {
        $data = $this->api->send(new ShowRecord($this->id))->json('data');

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : [];

        return new TargetState($data);
    }

    public function events(?TargetState $previous, TargetState $state): iterable
    {
        $payload = [
            'record' => $state->int('id'),
            'name' => $state->string('name'),
            'type' => $state->string('type'),
            'state' => $state->string('state'),
        ];

        if ($this->isDone($state)) {
            yield new WatchEvent('record.verified', $payload, $this->summary($state));

            return;
        }

        yield new WatchEvent('record.pending', $payload, sprintf(
            'Record is %s',
            (string) $state->string('state', 'pending'),
        ));
    }

    public function isDone(TargetState $state): bool
    {
        return $this->once || $state->string('state') === 'verified';
    }

    public function exitCode(TargetState $state): ExitCode
    {
        return $state->string('state') === 'verified' ? ExitCode::Ok : ExitCode::Timeout;
    }

    public function summary(TargetState $state): string
    {
        return $state->string('state') === 'verified'
            ? sprintf('Record %s is verified', (string) $state->string('name', ''))
            : sprintf('Record %s is still %s', (string) $state->string('name', ''), (string) $state->string('state', 'pending'));
    }

    public function header(TargetState $state): string
    {
        return sprintf(
            '%s %s %s',
            (string) $state->string('name', ''),
            (string) $state->string('type', ''),
            (string) $state->string('value', ''),
        );
    }
}
