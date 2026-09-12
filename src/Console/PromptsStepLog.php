<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Laravel\Prompts\Support\Logger;

/** A Prompts task: spinner, scrolling output, and a summary that stays. */
final class PromptsStepLog implements StepLog
{
    public function __construct(private readonly Logger $logger) {}

    public function line(string $message): void
    {
        $this->logger->line($message);
    }

    public function success(string $message): void
    {
        $this->logger->success($message);
    }

    public function warning(string $message): void
    {
        $this->logger->warning($message);
    }

    public function error(string $message): void
    {
        $this->logger->error($message);
    }

    public function subLabel(string $message): void
    {
        $this->logger->subLabel($message);
    }
}
