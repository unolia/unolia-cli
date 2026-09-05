<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Output\OutputInterface;
use Unolia\Cli\Console\Face;

use function Laravel\Prompts\table;

/**
 * The human face of a list. A box drawn table in a terminal, aligned columns when piped
 * so that grep, awk and less stay usable.
 */
final class TableRenderer
{
    public function __construct(
        private readonly Face $face,
        private readonly OutputInterface $promptsOutput,
    ) {}

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, string>  $columns
     */
    public function list(array $rows, array $columns): string
    {
        if ($rows === []) {
            return '';
        }

        $headers = array_values($columns);
        $matrix = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach (array_keys($columns) as $key) {
                $cells[] = $row[$key] ?? '';
            }

            $matrix[] = $cells;
        }

        return $this->face->interactive
            ? $this->boxed($headers, $matrix)
            : $this->aligned($headers, $matrix);
    }

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

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function boxed(array $headers, array $rows): string
    {
        $buffer = new BufferedConsoleOutput;
        Prompt::setOutput($buffer);

        try {
            table($headers, $rows);
        } finally {
            Prompt::setOutput($this->promptsOutput);
        }

        return rtrim($buffer->fetch(), "\n");
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function aligned(array $headers, array $rows): string
    {
        $widths = [];

        foreach ($headers as $index => $header) {
            $widths[$index] = mb_strwidth($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strwidth($cell));
            }
        }

        $lines = [$this->line(array_map(strtoupper(...), $headers), $widths)];

        foreach ($rows as $row) {
            $lines[] = $this->line($row, $widths);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, int>  $widths
     */
    private function line(array $cells, array $widths): string
    {
        $parts = [];
        $last = count($cells) - 1;

        foreach ($cells as $index => $cell) {
            $parts[] = $index === $last ? $cell : $this->pad($cell, $widths[$index] ?? 0);
        }

        return rtrim(implode('  ', $parts));
    }

    private function pad(string $value, int $width): string
    {
        $padding = $width - mb_strwidth($value);

        return $padding > 0 ? $value.str_repeat(' ', $padding) : $value;
    }
}
