<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Local\Php;

final class FakePhp extends Php
{
    public function __construct(private readonly ?string $version)
    {
        parent::__construct(new FakeHerd('/tmp'));
    }

    public function localVersion(string $directory): ?string
    {
        return $this->version;
    }
}
