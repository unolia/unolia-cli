<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Automations\ResumeAutomationRun;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\AutomationRunTarget;
use Unolia\Cli\Watch\Patience;
use Unolia\Cli\Watch\TargetState;

/**
 * An automation is a list of steps run one after the other, so a terminal
 * shows it as one task per step: the label, what the step reports while it
 * runs, and how it ended, each kept on screen. A run that parks waiting for
 * an answer asks its question right there, under the step, sends the answer
 * while the step's task spins, and carries on down the list.
 */
trait FollowsAutomationRuns
{
    use AnswersRuns;
    use Watches;

    private const STEP_DONE = ['completed', 'failed', 'skipped', 'cancelled', 'awaiting_input', 'rolled_back'];

    /**
     * @param  TargetState|null  $initial  a reading already in hand, so the list starts without a fetch
     * @param  array<string, mixed>|null  $answers  what to send when the step that is asking is reached, before asking
     */
    protected function followRunSteps(string $ulid, ?TargetState $initial = null, ?array $answers = null): ExitCode
    {
        $target = new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds());
        $poller = $this->runtime()->poller();
        $poller->trap();

        $timeout = $this->duration('timeout', 900);
        $started = time();
        $interval = $this->duration('interval', 3);
        $state = $initial ?? Patience::fetch($target, $poller, $interval);
        $pending = $answers;

        $this->out()->intro(sprintf(
            '%s · run %s',
            Str::scalar($state->get('automation.name'), 'Automation'),
            Str::shortId($state->string('ulid') ?? $ulid),
        ));

        // Steps appear as the run reaches them, so the list is re-read after
        // each one: the next step is the first not shown yet.
        $shown = [];

        while (true) {
            $step = self::nextStep($state, $shown);

            if ($step === null) {
                if ($target->isDone($state)) {
                    break;
                }

                $this->waitABit($started, $timeout, $state);
                $state = Patience::fetch($target, $poller, $interval);

                continue;
            }

            $id = $step['id'] ?? null;
            $shown[] = $id;
            $label = Str::scalar($step['label'] ?? $step['slug'] ?? null, 'Step '.count($shown));

            $outcome = $this->ask()->task($label, function (StepLog $log) use (&$state, &$pending, $ulid, $target, $poller, $interval, $id, $timeout, $started): string {
                $seen = null;

                while (true) {
                    $current = self::step($state, $id);
                    $stepState = Str::scalar($current['state'] ?? null, 'pending');

                    // The answer goes out under this step's spinner: the API
                    // runs the resumed step before it answers, which can take
                    // a while. A refused answer closes the task so the
                    // question can be asked again, outside it.
                    if ($stepState === 'awaiting_input' && $pending !== null) {
                        $log->subLabel('answering');
                        $inputs = $pending;
                        $pending = null;

                        try {
                            $state = new TargetState($this->fetch(new ResumeAutomationRun($ulid, ['inputs' => $inputs])), $state->meta);
                        } catch (ApiException $e) {
                            if ($e->status !== 422) {
                                throw $e;
                            }

                            $log->warning($e->toCliError()->getMessage());

                            return 'rejected';
                        }

                        $seen = null;

                        continue;
                    }

                    if (in_array($stepState, self::STEP_DONE, true)) {
                        return $this->closeStep($log, $current, $stepState);
                    }

                    // The run ended without this step: nothing more will happen to it.
                    if ($target->isDone($state)) {
                        $log->line('not run');

                        return 'not_run';
                    }

                    if ($stepState !== $seen) {
                        $log->subLabel($stepState === 'running' ? self::progress($current) : $stepState);
                        $seen = $stepState;
                    }

                    $this->waitABit($started, $timeout, $state);
                    $state = Patience::fetch($target, $poller, $interval);
                }
            });

            // The question, asked where the step stopped. The step then
            // re-enters the list as a fresh task that sends the answer.
            if (($outcome === 'awaiting_input' || $outcome === 'rejected') && $this->ask()->interactive()) {
                $pending = $this->askBlocks(self::blocksOf(self::step($state, $id)), $ulid);
                $shown = array_values(array_filter($shown, static fn (mixed $shownId): bool => $shownId !== $id));

                continue;
            }

            if ($outcome === 'awaiting_input' || ($outcome !== 'completed' && $outcome !== 'skipped' && $target->isDone($state))) {
                break;
            }
        }

        // The run's own last word, once every step has had its say.
        while (! $target->isDone($state)) {
            $this->waitABit($started, $timeout, $state);
            $state = Patience::fetch($target, $poller, $interval);
        }

        $summary = $target->summary($state);

        if ($state->string('state') === 'awaiting_input') {
            $this->out()->failure(sprintf('Run %s is waiting for an answer · unolia automation resume %s', Str::shortId($state->string('ulid')), Str::shortId($state->string('ulid'))));
        } elseif ($target->exitCode($state) === ExitCode::Ok) {
            $this->out()->outro($summary);
        } else {
            $this->out()->failure($summary);
        }

        return $target->exitCode($state);
    }

    /** The target long polls and Patience paces the reads; this only checks the clock. */
    private function waitABit(int $started, int $timeout, TargetState $state): void
    {
        if (time() - $started >= $timeout) {
            throw CliError::timeout('the run is still going', 'unolia automation watch '.Str::shortId($state->string('ulid')).' keeps following it.');
        }
    }

    /**
     * The first step, by position, not shown yet.
     *
     * @param  list<mixed>  $shown
     * @return array<string, mixed>|null
     */
    private static function nextStep(TargetState $state, array $shown): ?array
    {
        foreach (self::orderedSteps($state) as $step) {
            if (! in_array($step['id'] ?? null, $shown, true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function closeStep(StepLog $log, array $step, string $stepState): string
    {
        $summary = Str::scalar($step['output_summary'] ?? null, '');
        $took = RelativeTime::between(is_string($step['started_at'] ?? null) ? $step['started_at'] : null, is_string($step['finished_at'] ?? null) ? $step['finished_at'] : null);
        $duration = $took === null ? '' : ' in '.RelativeTime::duration($took);

        foreach (is_array($step['resources'] ?? null) ? $step['resources'] : [] as $resource) {
            if (is_array($resource) && is_string($resource['name'] ?? null)) {
                $seconds = $resource['duration_seconds'] ?? null;
                $log->line($resource['name'].(is_numeric($seconds) ? ' · '.RelativeTime::duration((int) $seconds) : ''));
            }
        }

        match ($stepState) {
            'completed' => $log->success(($summary === '' ? 'done' : $summary).$duration),
            'skipped' => $log->line('skipped'.($summary === '' ? '' : ': '.$summary)),
            'awaiting_input' => $log->warning('waiting for an answer'.($summary === '' ? '' : ': '.$summary)),
            default => $log->error(Str::scalar($step['error_message'] ?? null, $summary === '' ? $stepState : $summary)),
        };

        return $stepState;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private static function progress(array $step): string
    {
        $resources = is_array($step['resources'] ?? null) ? $step['resources'] : [];
        $attempts = $step['attempts'] ?? null;

        return trim(sprintf(
            'running%s%s',
            $resources === [] ? '' : ' on '.count($resources).' '.(count($resources) === 1 ? 'resource' : 'resources'),
            is_numeric($attempts) && (int) $attempts > 1 ? ', attempt '.$attempts : '',
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedSteps(TargetState $state): array
    {
        $steps = [];

        foreach (is_array($state->get('steps')) ? $state->get('steps') : [] as $step) {
            if (is_array($step)) {
                $steps[] = $step;
            }
        }

        usort($steps, static fn (array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(TargetState $state, mixed $id): array
    {
        foreach (self::orderedSteps($state) as $step) {
            if (($step['id'] ?? null) === $id) {
                return $step;
            }
        }

        return [];
    }
}
