<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Support\Notifier;
use Unolia\Cli\Watch\Target;
use Unolia\Cli\Watch\Watcher;
use Unolia\Cli\Watch\WatchResult;

/**
 * The flags and the loop shared by every command that follows something to its end.
 */
trait Watches
{
    protected function addWatchOptions(string $defaultTimeout = '15m', int $defaultInterval = 3): void
    {
        $this->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Seconds between checks', (string) $defaultInterval);
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Give up waiting after this long', $defaultTimeout);
        $this->addOption('notify', null, InputOption::VALUE_NONE, 'Send a desktop notification at the end');
    }

    protected function follow(Target $target): WatchResult
    {
        $watcher = new Watcher(
            $this->out(),
            $this->runtime()->poller(),
            $this->runtime()->get(Notifier::class),
        );

        return $watcher->run(
            $target,
            $this->duration('interval', 3),
            $this->duration('timeout', 900),
            $this->optionBool('notify'),
        );
    }

    /** How long a long poll may hold the connection, always under the request timeout. */
    protected function waitSeconds(): int
    {
        return min(20, max(2, $this->duration('interval', 3) * 10));
    }
}
