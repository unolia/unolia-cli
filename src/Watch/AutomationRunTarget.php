<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Automations\ShowAutomationRun;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * An automation run, step by step. A run that parks waiting for an answer is done for
 * the watch and exits 7, because retrying it is the wrong move.
 */
final class AutomationRunTarget implements Target
{
    public const TERMINAL = ['completed', 'failed', 'rolled_back', 'cancelled', 'awaiting_input'];

    private bool $first = true;

    public function __construct(
        private readonly Client $api,
        private readonly string $ulid,
        private readonly int $wait = 20,
    ) {}

    public function fetch(): TargetState
    {
        $wait = $this->first ? 0 : $this->wait;
        $this->first = false;

        $body = $this->api->send(new ShowAutomationRun($this->ulid, ['wait' => $wait]))->json();
        $body = is_array($body) ? $body : [];

        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return new TargetState($data, $meta);
    }

    public function events(?TargetState $previous, TargetState $state): iterable
    {
        if ($previous === null) {
            yield new WatchEvent('run.started', [
                'run' => $state->string('ulid'),
                'automation' => $state->get('automation.name'),
                'state' => $state->string('state'),
            ], sprintf(
                'Run %s of %s is %s',
                Str::shortId($state->string('ulid')),
                (string) ($state->get('automation.name') ?? 'an automation'),
                (string) $state->string('state', 'running'),
            ));
        }

        foreach ($this->changedSteps($previous, $state) as $step) {
            $stepState = (string) ($step['state'] ?? '');
            $type = match ($stepState) {
                'running' => 'step.started',
                'completed' => 'step.completed',
                'failed' => 'step.failed',
                default => 'step.'.$stepState,
            };

            yield new WatchEvent($type, ['step' => $step], sprintf(
                '%s %s',
                (string) ($step['label'] ?? $step['slug'] ?? ''),
                $stepState,
            ));
        }

        if ($state->string('state') === 'awaiting_input') {
            yield new WatchEvent('run.awaiting_input', [
                'run' => $state->string('ulid'),
                'step' => $this->awaitingStep($state),
            ], 'This run is waiting for an answer. Resume it with unolia automation resume '.Str::shortId($state->string('ulid')));
        }

        if ($this->isDone($state) && $state->string('state') !== 'awaiting_input') {
            yield new WatchEvent('run.'.(string) $state->string('state', 'completed'), [
                'run' => $state->string('ulid'),
                'state' => $state->string('state'),
                'summary' => $state->string('summary'),
                'duration_seconds' => $state->int('duration_seconds'),
            ], $this->summary($state));
        }
    }

    public function isDone(TargetState $state): bool
    {
        return in_array((string) $state->string('state', ''), self::TERMINAL, true);
    }

    public function exitCode(TargetState $state): ExitCode
    {
        return match ($state->string('state')) {
            'completed' => ExitCode::Ok,
            'awaiting_input' => ExitCode::AwaitingInput,
            default => ExitCode::RemoteFailure,
        };
    }

    public function summary(TargetState $state): string
    {
        $duration = $state->int('duration_seconds');

        return sprintf(
            'Run %s %s%s',
            Str::shortId($state->string('ulid')),
            (string) $state->string('state', 'finished'),
            $duration === null ? '' : ' in '.RelativeTime::duration($duration),
        );
    }

    public function header(TargetState $state): ?string
    {
        $name = $state->get('automation.name');

        return is_string($name) ? $name : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function awaitingStep(TargetState $state): ?array
    {
        foreach ($this->steps($state) as $step) {
            if (($step['state'] ?? null) === 'awaiting_input') {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function steps(TargetState $state): array
    {
        $steps = $state->get('steps', []);
        $list = [];

        foreach (is_array($steps) ? $steps : [] as $step) {
            if (is_array($step)) {
                $list[] = $step;
            }
        }

        return $list;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function changedSteps(?TargetState $previous, TargetState $state): array
    {
        $before = [];

        foreach ($previous === null ? [] : $this->steps($previous) as $step) {
            $id = $step['id'] ?? null;

            if (is_scalar($id)) {
                $before[(string) $id] = $step['state'] ?? null;
            }
        }

        $changed = [];

        foreach ($this->steps($state) as $step) {
            $id = $step['id'] ?? null;

            if (! is_scalar($id)) {
                continue;
            }

            $current = $step['state'] ?? null;

            if ($previous === null) {
                if (in_array($current, ['completed', 'failed', 'running'], true)) {
                    $changed[] = $step;
                }

                continue;
            }

            if (($before[(string) $id] ?? null) !== $current) {
                $changed[] = $step;
            }
        }

        return $changed;
    }
}
