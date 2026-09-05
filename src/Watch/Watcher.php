<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Poller;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Out;
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

    public function run(Target $target, int $interval = 3, ?int $timeout = null, bool $notify = false): WatchResult
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

                if ($table && $header !== null) {
                    $this->out->line($header);
                }
            }

            foreach ($target->events($previous, $state) as $event) {
                $this->out->event($event->toArray(), $event->line);
            }

            if ($target->isDone($state)) {
                $summary = $target->summary($state);

                if ($table && $summary !== '') {
                    $this->out->info($summary);
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
