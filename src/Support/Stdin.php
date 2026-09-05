<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

/**
 * Reading piped input, isolated so tests never block on a terminal.
 */
class Stdin
{
    public function read(): string
    {
        $contents = @file_get_contents('php://stdin');

        return $contents === false ? '' : $contents;
    }
}
