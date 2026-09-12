<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

/**
 * Where a long step reports as it runs: the lines a tool prints, the outcome of
 * each part, and the part in progress. On a terminal this is a Prompts task, in
 * a pipe it is silence, in tests it is plain lines.
 */
interface StepLog
{
    public function line(string $message): void;

    public function success(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;

    public function subLabel(string $message): void;
}
