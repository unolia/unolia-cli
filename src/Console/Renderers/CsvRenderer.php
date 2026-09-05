<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

use Unolia\Cli\Support\Str;

/**
 * RFC 4180 output. Nested values are flattened to a scalar so a spreadsheet stays readable.
 */
final class CsvRenderer
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     */
    public function list(array $rows, array $columns): string
    {
        $keys = $columns === [] ? $this->keysOf($rows) : array_keys($columns);
        $headers = $columns === [] ? $keys : array_values($columns);

        $lines = [$this->line($headers)];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($keys as $key) {
                $cells[] = Str::scalar($row[$key] ?? null, '');
            }

            $lines[] = $this->line($cells);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function record(array $record): string
    {
        $lines = [$this->line(['key', 'value'])];

        foreach ($record as $key => $value) {
            $lines[] = $this->line([$key, Str::scalar($value, '')]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $cells
     */
    public function line(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return implode(',', $cells);
        }

        fputcsv($handle, $cells, ',', '"', '\\', "\n");
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return rtrim($line, "\n");
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function keysOf(array $rows): array
    {
        $keys = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $keys[$key] = true;
            }
        }

        return array_map(strval(...), array_keys($keys));
    }
}
