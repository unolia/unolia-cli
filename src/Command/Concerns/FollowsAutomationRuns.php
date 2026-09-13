<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Terminal;
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
    use AsksInputBlocks;
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
            $label = self::labelFor($step, $state, 'Step '.count($shown));
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
                    $this->stepLine($label, $current, $stepState, self::isChild($current));
                    $outcome = $stepState;

                    continue;
                }

                // The run ended without this step: nothing more will happen to it.
                if ($target->isDone($state)) {
                    $this->out()->formatted(sprintf('%s<fg=gray>○ %s · not run</>', self::indent($current), $label));
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
                $pending = $this->askBlocks(self::blocksOf(self::step($state, $id)), sprintf('the run is still waiting · unolia automation resume %s answers it', $short));
                $shown = array_values(array_filter($shown, static fn (mixed $shownId): bool => $shownId !== $id));

                continue;
            }

            // A step that failed ends the list only when it took the run
            // down with it: a fan-out may lose a child and carry on.
            if ($outcome === 'awaiting_input' || ($outcome !== 'completed' && $outcome !== 'skipped' && in_array($state->string('state'), ['failed', 'cancelled', 'rolled_back'], true))) {
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
     * The servers it touched follow, one per line, indented, then what it
     * wrote for a reader. A child of a fan-out sits one level in under its
     * parent and, when it went well, keeps its notes to itself, the way the
     * run page folds them: six servers that each say the key went in is one
     * line of information, not six paragraphs.
     *
     * @param  array<string, mixed>  $step
     */
    private function stepLine(string $label, array $step, string $stepState, bool $child): void
    {
        $indent = self::indent($step);
        $summary = Str::scalar($step['output_summary'] ?? null, '');
        $took = RelativeTime::between(is_string($step['started_at'] ?? null) ? $step['started_at'] : null, is_string($step['finished_at'] ?? null) ? $step['finished_at'] : null);
        $tail = array_values(array_filter([$summary, $took === null ? '' : RelativeTime::duration($took)], static fn (string $part): bool => $part !== ''));

        $this->out()->formatted($indent.match ($stepState) {
            'completed' => sprintf('<fg=green>✓</> %s%s', $label, self::dim($tail)),
            'skipped' => sprintf('<fg=gray>○ %s · skipped%s</>', $label, $summary === '' ? '' : ': '.$summary),
            'awaiting_input' => sprintf('<fg=yellow>◐</> %s <fg=gray>· waiting for an answer</>', $label),
            default => sprintf('<fg=red>✕</> %s <fg=gray>·</> <fg=red>%s</>', $label, Str::scalar($step['error_message'] ?? null, $summary === '' ? str_replace('_', ' ', $stepState) : $summary)),
        });

        foreach (is_array($step['resources'] ?? null) ? $step['resources'] : [] as $resource) {
            if (is_array($resource) && is_string($resource['name'] ?? null)) {
                $seconds = $resource['duration_seconds'] ?? null;
                $this->out()->formatted(sprintf('%s  <fg=gray>%s%s</>', $indent, $resource['name'], is_numeric($seconds) ? ' · '.RelativeTime::duration((int) $seconds) : ''));
            }
        }

        if ($child && ($stepState === 'completed' || $stepState === 'skipped')) {
            return;
        }

        foreach (is_array($step['output_blocks'] ?? null) ? $step['output_blocks'] : [] as $block) {
            if (is_array($block)) {
                $this->outputBlock($block, $indent);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private static function isChild(array $step): bool
    {
        return ($step['parent_id'] ?? null) !== null;
    }

    /**
     * Where a step's line starts: one space, two more for a child.
     *
     * @param  array<string, mixed>  $step
     */
    private static function indent(array $step): string
    {
        return self::isChild($step) ? '   ' : ' ';
    }

    /**
     * A step's label as the list shows it. The children of a fan-out all ran
     * the same thing on different targets, so their labels differ only in
     * the last words: the shared start is lifted off and each line carries
     * what varies, "web-01" rather than "Install temp SSH key on web-01".
     * Only a word boundary is cut, and only when there are siblings to
     * compare with.
     *
     * @param  array<string, mixed>  $step
     */
    private static function labelFor(array $step, TargetState $state, string $fallback): string
    {
        $label = Str::scalar($step['label'] ?? $step['slug'] ?? null, $fallback);

        if (! self::isChild($step)) {
            return $label;
        }

        $siblings = [];

        foreach (self::orderedSteps($state) as $other) {
            if (($other['parent_id'] ?? null) === $step['parent_id'] && is_string($other['label'] ?? null)) {
                $siblings[] = $other['label'];
            }
        }

        if (count($siblings) < 2) {
            return $label;
        }

        $shared = array_shift($siblings);

        foreach ($siblings as $sibling) {
            $length = min(strlen($shared), strlen($sibling));
            $i = 0;

            while ($i < $length && $shared[$i] === $sibling[$i]) {
                $i++;
            }

            $shared = substr($shared, 0, $i);
        }

        $shared = preg_match('/^(.*\s)\S*$/u', $shared, $match) === 1 ? $match[1] : '';
        $short = trim(substr($label, strlen($shared)));

        return $short === '' || $shared === '' ? $label : $short;
    }

    /** Room for what a step wrote, under its line, past the glyph. */
    private const BLOCK_INDENT = '    ';

    /**
     * What the step wrote for a reader, as the run page shows it under the
     * step: a table as aligned columns with a dim header, a note as dim text
     * wrapped to the terminal, its markdown marks dropped.
     *
     * @param  array<string, mixed>  $block
     */
    private function outputBlock(array $block, string $indent = ' '): void
    {
        $pad = $indent.self::BLOCK_INDENT;
        $width = max(40, (new Terminal)->getWidth() - strlen($pad) - 1);

        if (($block['kind'] ?? null) === 'markdown' && is_string($block['body'] ?? null)) {
            $text = (string) preg_replace(['/\*\*(.+?)\*\*/s', '/`([^`]*)`/', '/^#+\s*/m', '/^\s*[-*]\s+/m'], ['$1', '$1', '', '· '], $block['body']);

            foreach (Str::wrap($text, $width) as $line) {
                $this->out()->formatted($line === '' ? '' : $pad.'<fg=gray>'.OutputFormatter::escape($line).'</>');
            }

            return;
        }

        if (($block['kind'] ?? null) !== 'table' || ! is_array($block['rows'] ?? null)) {
            return;
        }

        $headers = array_values(array_map(static fn (mixed $cell): string => Str::scalar($cell, ''), is_array($block['headers'] ?? null) ? $block['headers'] : []));
        $rows = [];

        foreach ($block['rows'] as $row) {
            if (is_array($row)) {
                $rows[] = array_values(array_map(static fn (mixed $cell): string => Str::scalar($cell, ''), $row));
            }
        }

        $widths = [];

        foreach ([$headers, ...$rows] as $cells) {
            foreach ($cells as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strwidth($cell));
            }
        }

        $line = static function (array $cells) use ($widths): string {
            $out = [];

            foreach ($widths as $index => $columnWidth) {
                $cell = $cells[$index] ?? '';
                $out[] = $index === array_key_last($widths) ? $cell : $cell.str_repeat(' ', $columnWidth - mb_strwidth($cell));
            }

            return rtrim(implode('  ', $out));
        };

        if ($headers !== []) {
            $this->out()->formatted($pad.'<fg=gray>'.OutputFormatter::escape($line($headers)).'</>');
        }

        foreach ($rows as $row) {
            $this->out()->formatted($pad.OutputFormatter::escape($line($row)));
        }

        if (is_string($block['caption'] ?? null) && $block['caption'] !== '') {
            $this->out()->formatted($pad.'<fg=gray>'.OutputFormatter::escape($block['caption']).'</>');
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

        usort($steps, static fn (array $a, array $b): int => [(float) ($a['position'] ?? 0), (int) ($a['id'] ?? 0)] <=> [(float) ($b['position'] ?? 0), (int) ($b['id'] ?? 0)]);

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
