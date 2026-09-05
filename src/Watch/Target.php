<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Console\ExitCode;

/**
 * Something worth watching: a deployment, a CI run, an automation run, a DNS record.
 */
interface Target
{
    /** One API call, which may long poll. */
    public function fetch(): TargetState;

    /**
     * What changed between two readings.
     *
     * @return iterable<WatchEvent>
     */
    public function events(?TargetState $previous, TargetState $state): iterable;

    public function isDone(TargetState $state): bool;

    public function exitCode(TargetState $state): ExitCode;

    /** The closing line a person reads. */
    public function summary(TargetState $state): string;

    /** The header printed once before the first reading. */
    public function header(TargetState $state): ?string;
}
