<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

/** The pipe face: the step runs, the report afterwards says what happened. */
final class QuietStepLog implements StepLog
{
    public function line(string $message): void {}

    public function success(string $message): void {}

    public function warning(string $message): void {}

    public function error(string $message): void {}

    public function subLabel(string $message): void {}
}
