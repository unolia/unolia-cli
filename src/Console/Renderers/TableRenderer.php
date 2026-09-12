<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

/**
 * The human face of one record: a label column and a value column, aligned.
 * Lists are drawn by the StyledTableRenderer.
 */
final class TableRenderer
{
    /**
     * @param  array<string, string>  $record
     */
    public function record(array $record): string
    {
        if ($record === []) {
            return '';
        }

        $width = 0;

        foreach (array_keys($record) as $label) {
            $width = max($width, mb_strwidth($label));
        }

        $lines = [];

        foreach ($record as $label => $value) {
            $lines[] = rtrim($this->pad($label, $width).'  '.$value);
        }

        return implode("\n", $lines);
    }

    private function pad(string $value, int $width): string
    {
        $padding = $width - mb_strwidth($value);

        return $padding > 0 ? $value.str_repeat(' ', $padding) : $value;
    }
}
