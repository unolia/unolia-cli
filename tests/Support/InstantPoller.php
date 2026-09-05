<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Api\Poller;

final class InstantPoller extends Poller
{
    public function __construct()
    {
        parent::__construct(static fn (int $seconds): null => null);
    }
}
