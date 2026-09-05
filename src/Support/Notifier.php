<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use Throwable;

use function Laravel\Prompts\notify;

/**
 * Desktop notification at the end of a long watch. Best effort, never fatal.
 */
class Notifier
{
    public function send(string $title, string $body = ''): void
    {
        try {
            notify($title, $body);
        } catch (Throwable) {
            // A missing notifier is not a reason to fail the command.
        }
    }
}
