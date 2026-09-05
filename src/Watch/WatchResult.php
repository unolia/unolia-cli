<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Console\ExitCode;

final readonly class WatchResult
{
    public function __construct(
        public ExitCode $exitCode,
        public TargetState $state,
    ) {}
}
