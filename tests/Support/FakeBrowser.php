<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Support\Browser;

final class FakeBrowser extends Browser
{
    /** @var list<string> */
    public array $opened = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function open(string $url, ?string $override = null): bool
    {
        $this->opened[] = $url;

        return true;
    }
}
