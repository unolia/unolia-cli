<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Support\Stdin;

final class FakeStdin extends Stdin
{
    public function __construct(private readonly string $contents = '') {}

    public function read(): string
    {
        return $this->contents;
    }
}
