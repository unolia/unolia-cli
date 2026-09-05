<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Repositories\ShowAction;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;

/**
 * A CI run and its jobs.
 */
final class ActionTarget implements Target
{
    private bool $first = true;

    public function __construct(
        private readonly Client $api,
        private readonly int $id,
        private readonly int $wait = 20,
    ) {}

    public function fetch(): TargetState
    {
        $wait = $this->first ? 0 : $this->wait;
        $this->first = false;

        $body = $this->api->send(new ShowAction($this->id, ['wait' => $wait]))->json();
        $body = is_array($body) ? $body : [];

        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return new TargetState($data, $meta);
    }

    public function events(?TargetState $previous, TargetState $state): iterable
    {
        $jobs = $this->jobs($state);

        if ($previous === null) {
            yield new WatchEvent('run.updated', [
                'run' => $state->int('run_number'),
                'action' => $state->int('id'),
                'status' => $state->string('status'),
                'jobs' => $jobs,
            ], sprintf(
                'Run #%s %s is %s',
                (string) $state->int('run_number'),
                (string) $state->string('name', ''),
                (string) $state->string('status', 'queued'),
            ));
        }

        foreach ($this->completedSince($previous, $state) as $job) {
            yield new WatchEvent('job.completed', ['job' => $job], sprintf(
                '%s %s%s',
                ($job['conclusion'] ?? null) === 'success' ? 'ok' : 'x',
                (string) ($job['name'] ?? ''),
                isset($job['duration_seconds']) && is_numeric($job['duration_seconds'])
                    ? ' in '.RelativeTime::duration((int) $job['duration_seconds'])
                    : '',
            ));
        }

        if ($this->isDone($state)) {
            yield new WatchEvent('run.completed', [
                'run' => $state->int('run_number'),
                'action' => $state->int('id'),
                'conclusion' => $state->string('conclusion'),
                'duration_seconds' => $state->int('duration_seconds'),
                'url' => $state->string('html_url'),
            ], $this->summary($state));
        }
    }

    public function isDone(TargetState $state): bool
    {
        return $state->string('status') === 'completed';
    }

    public function exitCode(TargetState $state): ExitCode
    {
        return $state->string('conclusion') === 'success' ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    public function summary(TargetState $state): string
    {
        $duration = $state->int('duration_seconds');

        return sprintf(
            'Run #%s %s%s',
            (string) $state->int('run_number'),
            (string) $state->string('conclusion', 'completed'),
            $duration === null ? '' : ' in '.RelativeTime::duration($duration),
        );
    }

    public function header(TargetState $state): ?string
    {
        $parts = array_filter([
            $state->get('repository.full_name'),
            $state->string('branch'),
            $state->string('name'),
        ], is_string(...));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jobs(TargetState $state): array
    {
        $jobs = $state->get('jobs', []);
        $list = [];

        foreach (is_array($jobs) ? $jobs : [] as $job) {
            if (is_array($job)) {
                $list[] = $job;
            }
        }

        return $list;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedSince(?TargetState $previous, TargetState $state): array
    {
        $before = [];

        foreach ($previous === null ? [] : $this->jobs($previous) as $job) {
            $id = $job['id'] ?? null;

            if (is_scalar($id)) {
                $before[(string) $id] = $job['status'] ?? null;
            }
        }

        $completed = [];

        foreach ($this->jobs($state) as $job) {
            $id = $job['id'] ?? null;

            if (! is_scalar($id) || ($job['status'] ?? null) !== 'completed') {
                continue;
            }

            if (($before[(string) $id] ?? null) !== 'completed') {
                $completed[] = $job;
            }
        }

        return $completed;
    }
}
