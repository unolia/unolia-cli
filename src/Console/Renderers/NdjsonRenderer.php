<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

final class NdjsonRenderer
{
    /**
     * @param  list<mixed>  $rows
     */
    public function list(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = JsonRenderer::encode($row);
        }

        return implode("\n", $lines);
    }

    public function line(mixed $row): string
    {
        return JsonRenderer::encode($row);
    }
}
