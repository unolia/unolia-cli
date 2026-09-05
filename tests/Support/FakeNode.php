<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Local\Node;

final class FakeNode extends Node
{
    public function __construct(private readonly ?string $version = '22.11.0') {}

    public function localVersion(): ?string
    {
        return $this->version;
    }
}
