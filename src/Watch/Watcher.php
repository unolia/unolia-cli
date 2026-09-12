<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Poller;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Notifier;

/**
 * The loop every watch shares. Prints what changed, stops when the target settles, and
 * says the remote work continues when you interrupt it.
 */
final class Watcher
{
    public function __construct(
        private readonly Out $out,
        private readonly Poller $poller,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Follow the target until it is done. With a $log the events go into a
     * task (the tool's lines scroll, the rest updates the sub-label, the
     * summary is kept as success or failure) instead of the plain output.
     */
    public function run(Target $target, int $interval = 3, ?int $timeout = null, bool $notify = false, ?StepLog $log = null): WatchResult
    {
        $this->poller->trap();

        $table = $this->out->face()->format === Format::Table;
        $started = time();
        $previous = null;
        $headerPrinted = false;

        while (true) {
            $state = $target->fetch();

            if (! $headerPrinted) {
                $headerPrinted = true;
                $header = $target->header($state);

                if ($table && $header !== null && $log === null) {
                    $this->out->intro($header);
                }
            }

            $done = $target->isDone($state);
            $summary = $done ? $target->summary($state) : null;

            foreach ($target->events($previous, $state) as $event) {
                if ($log === null) {
                    // The header says what is followed and the footer how it
                    // ended, so neither is repeated as an event line on the
                    // table face: the summary never is, and the introduction
                    // is not when the thing had already finished on first look.
                    $introduces = str_ends_with($event->type, '.started') || str_ends_with($event->type, '.updated');
                    $repeatsFrame = $table && $done && ($event->line === $summary || ($previous === null && $introduces));

                    $this->out->event($event->toArray(), $repeatsFrame ? '' : $event->line);
                } elseif (str_ends_with($event->type, '.output')) {
                    $log->line(ltrim($event->line));
                } elseif (! $target->isDone($state)) {
                    $log->subLabel($event->line);
                }
            }

            if ($done && $summary !== null) {

                if ($log !== null && $summary !== '') {
                    $log->subLabel('');
                    $target->exitCode($state) === ExitCode::Ok ? $log->success($summary) : $log->error($summary);
                } elseif ($table && $summary !== '') {
                    $target->exitCode($state) === ExitCode::Ok ? $this->out->outro($summary) : $this->out->failure($summary);
                }

                if ($notify && $summary !== '') {
                    $this->notifier->send('Unolia', $summary);
                }

                return new WatchResult($target->exitCode($state), $state);
            }

            if ($timeout !== null && time() - $started >= $timeout) {
                throw CliError::timeout(
                    'gave up waiting, the remote work is still running',
                    'Raise --timeout, or follow it again later.',
                );
            }

            $previous = $state;
            $this->poller->sleep($this->poller->intervalFor($state->body(), $interval));
        }
    }
}
