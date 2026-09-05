<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

class Node extends Tool
{
    public function localVersion(): ?string
    {
        $result = $this->run('node', ['-v']);

        if (! $result->successful()) {
            return null;
        }

        return preg_match('/(\d+\.\d+\.\d+)/', $result->trimmed(), $matches) === 1 ? $matches[1] : null;
    }
}
