<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Automations\ResumeAutomationRun;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\AutomationRunTarget;
use Unolia\Cli\Watch\Patience;
use Unolia\Cli\Watch\TargetState;

/**
 * An automation is a list of steps run one after the other, so a terminal
 * shows it as a checklist: one line per step, with what the step reported
 * and how long it took, and a spinner on the step that is going. A run that
 * parks waiting for an answer asks its question right there, under the
 * step, sends the answer under the same spinner, and carries on down the
 * list.
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
        $patience = new Patience($target, $poller, $this->duration('interval', 3));
        $short = Str::shortId($ulid);
        $state = $initial ?? $this->ask()->spin(sprintf('Reading run %s', $short), $patience->fetch(...));
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
                $state = $patience->fetch();

                continue;
            }

            $id = $step['id'] ?? null;
            $shown[] = $id;
            $label = Str::scalar($step['label'] ?? $step['slug'] ?? null, 'Step '.count($shown));
            $outcome = null;

            // The step's line is written once it is over. Until then a spinner
            // carries the label and what the step says about itself.
            while ($outcome === null) {
                $current = self::step($state, $id);
                $stepState = Str::scalar($current['state'] ?? null, 'pending');

                if ($stepState === 'awaiting_input' && $pending !== null) {
                    // The API runs the resumed step before it answers, which
                    // can take a while: the answer goes out under the spinner.
                    $inputs = $pending;
                    $pending = null;

                    try {
                        $state = $this->ask()->spin(
                            sprintf('%s · answering', $label),
                            fn (): TargetState => new TargetState($this->fetch(new ResumeAutomationRun($ulid, ['inputs' => $inputs])), $state->meta),
                        );
                    } catch (ApiException $e) {
                        if ($e->status !== 422) {
                            throw $e;
                        }

                        $this->out()->warn($e->toCliError()->getMessage());
                        $outcome = 'rejected';
                    }

                    continue;
                }

                if (in_array($stepState, self::STEP_DONE, true)) {
                    $this->stepLine($label, $current, $stepState);
                    $outcome = $stepState;

                    continue;
                }

                // The run ended without this step: nothing more will happen to it.
                if ($target->isDone($state)) {
                    $this->out()->formatted(sprintf(' <fg=gray>○ %s · not run</>', $label));
                    $outcome = 'not_run';

                    continue;
                }

                $this->waitABit($started, $timeout, $state);
                $state = $this->ask()->spin(
                    sprintf('%s · %s', $label, $stepState === 'running' ? self::progress($current) : $stepState),
                    $patience->fetch(...),
                );
            }

            // The question, asked where the step stopped. The step then goes
            // back on the list, to send the answer and be written once over.
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
            $state = $this->ask()->spin(sprintf('Run %s · %s', $short, Str::scalar($state->string('state'), 'running')), $patience->fetch(...));
        }

        $summary = $target->summary($state);

        if ($state->string('state') === 'awaiting_input') {
            $this->out()->failure(sprintf('Run %s is waiting for an answer · unolia automation resume %s', $short, $short));
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
     * One line for a step that is over: a glyph in the colour of how it
     * ended, the label, then what it reported and how long it took, dim.
     * The servers it touched follow, one per line, indented.
     *
     * @param  array<string, mixed>  $step
     */
    private function stepLine(string $label, array $step, string $stepState): void
    {
        $summary = Str::scalar($step['output_summary'] ?? null, '');
        $took = RelativeTime::between(is_string($step['started_at'] ?? null) ? $step['started_at'] : null, is_string($step['finished_at'] ?? null) ? $step['finished_at'] : null);
        $tail = array_values(array_filter([$summary, $took === null ? '' : RelativeTime::duration($took)], static fn (string $part): bool => $part !== ''));

        $this->out()->formatted(match ($stepState) {
            'completed' => sprintf(' <fg=green>✓</> %s%s', $label, self::dim($tail)),
            'skipped' => sprintf(' <fg=gray>○ %s · skipped%s</>', $label, $summary === '' ? '' : ': '.$summary),
            'awaiting_input' => sprintf(' <fg=yellow>◐</> %s <fg=gray>· waiting for an answer</>', $label),
            default => sprintf(' <fg=red>✕</> %s <fg=gray>·</> <fg=red>%s</>', $label, Str::scalar($step['error_message'] ?? null, $summary === '' ? str_replace('_', ' ', $stepState) : $summary)),
        });

        foreach (is_array($step['resources'] ?? null) ? $step['resources'] : [] as $resource) {
            if (is_array($resource) && is_string($resource['name'] ?? null)) {
                $seconds = $resource['duration_seconds'] ?? null;
                $this->out()->formatted(sprintf('   <fg=gray>%s%s</>', $resource['name'], is_numeric($seconds) ? ' · '.RelativeTime::duration((int) $seconds) : ''));
            }
        }
    }

    /**
     * @param  list<string>  $parts
     */
    private static function dim(array $parts): string
    {
        return $parts === [] ? '' : sprintf(' <fg=gray>· %s</>', implode(' · ', $parts));
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
