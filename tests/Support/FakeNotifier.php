<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Support\Notifier;

final class FakeNotifier extends Notifier
{
    /** @var list<string> */
    public array $sent = [];

    public function send(string $title, string $body = ''): void
    {
        $this->sent[] = $title.': '.$body;
    }
}
