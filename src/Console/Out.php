<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Output\OutputInterface;
use Unolia\Cli\Console\Renderers\CsvRenderer;
use Unolia\Cli\Console\Renderers\JsonRenderer;
use Unolia\Cli\Console\Renderers\NdjsonRenderer;
use Unolia\Cli\Console\Renderers\StyledTableRenderer;
use Unolia\Cli\Console\Renderers\TableRenderer;
use Unolia\Cli\Console\Renderers\YamlRenderer;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Str;

/**
 * The single place a command writes to. Data goes to stdout in the requested format,
 * messages go to stdout on the table face and to stderr otherwise, errors always to stderr.
 */
final class Out
{
    private readonly TableRenderer $table;

    private readonly JsonRenderer $json;

    private readonly NdjsonRenderer $ndjson;

    private readonly CsvRenderer $csv;

    private readonly YamlRenderer $yaml;

    public function __construct(
        private readonly Face $face,
        private readonly OutputInterface $stdout,
        private readonly OutputInterface $stderr,
        private readonly Jq $jq = new Jq,
    ) {
        $this->table = new TableRenderer($face, $stdout);
        $this->json = new JsonRenderer($face->interactive);
        $this->ndjson = new NdjsonRenderer;
        $this->csv = new CsvRenderer;
        $this->yaml = new YamlRenderer;
    }

    public function face(): Face
    {
        return $this->face;
    }

    /**
     * A list of rows. Keys are stable across formats, $columns maps key to table header,
     * $decorate returns the display strings for the table face only.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     * @param  (callable(array<string, mixed>): array<string, string>)|null  $decorate
     */
    public function list(array $rows, array $columns, ?callable $decorate = null, ?string $empty = null): void
    {
        if ($this->face->format === Format::Table) {
            if ($rows === []) {
                if ($empty !== null) {
                    $this->note($empty);
                }

                return;
            }

            $display = [];

            foreach ($rows as $row) {
                $display[] = $this->displayRow($row, $columns, $decorate);
            }

            $this->write($this->table->list($display, $columns));

            return;
        }

        $this->writeData(array_values($rows), $columns);
    }

    /**
     * A list drawn by a Table: the styled face on a terminal, plain aligned text
     * in a pipe, and the table's data fields on every other format.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function table(array $rows, Table $table, ?string $empty = null): void
    {
        if ($this->face->format !== Format::Table) {
            $this->writeData(array_values($rows), $table->dataFields());

            return;
        }

        if ($rows === []) {
            if ($empty !== null) {
                $this->note($empty);
            }

            return;
        }

        $text = (new StyledTableRenderer($this->face))->render($rows, $table);

        if ($this->face->interactive) {
            // A breath before the table, so it does not sit on the prompt line.
            $this->stdout->writeln('');
            $this->stdout->writeln($text);

            return;
        }

        $this->write($text);
    }

    /**
     * One record. Two columns on the table face, one object otherwise.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $labels
     */
    public function record(array $record, array $labels = []): void
    {
        if ($this->face->format === Format::Table) {
            $lines = [];

            foreach ($record as $key => $value) {
                $label = $labels[$key] ?? Str::headline($key);
                $lines[$label] = Str::scalar($value);
            }

            $this->write($this->table->record($lines));

            return;
        }

        $this->writeData($record, []);
    }

    /**
     * One event of a stream. The table face prints the prepared line, ndjson one object per line,
     * the json face stays silent because the command prints a final record.
     *
     * @param  array<string, mixed>  $event
     */
    public function event(array $event, string $ttyLine): void
    {
        match ($this->face->format) {
            Format::Table => $this->write($ttyLine),
            Format::Ndjson => $this->write($this->applyJq($this->ndjson->line($event))),
            Format::Csv => $this->write($this->csv->line(array_values(array_map(static fn (mixed $value): string => Str::scalar($value, ''), $event)))),
            Format::Json, Format::Yaml => null,
        };
    }

    public function info(string $line): void
    {
        $this->message($this->face->interactive ? '<info>✓</info> '.$line : $line);
    }

    public function note(string $line): void
    {
        $this->message($this->face->interactive ? '<fg=gray>'.$line.'</>' : $line);
    }

    public function warn(string $line): void
    {
        $this->stderr->writeln($this->face->interactive ? '<comment>!</comment> '.$line : '! '.$line);
    }

    public function error(CliError $error): void
    {
        if ($this->face->format === Format::Json || $this->face->format === Format::Ndjson) {
            $this->stderr->writeln(JsonRenderer::encode(['error' => $error->envelope()], $this->face->interactive));

            return;
        }

        $this->stderr->writeln($this->face->color
            ? '<fg=red>error:</> '.$error->getMessage()
            : 'error: '.$error->getMessage());

        if ($error->hint !== null && $error->hint !== '') {
            $this->stderr->writeln($this->face->color ? '<fg=gray>'.$error->hint.'</>' : $error->hint);
        }
    }

    /** Verbatim text on stdout, used for logs and raw API bodies. */
    public function raw(string $text): void
    {
        $this->stdout->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    public function line(string $text = ''): void
    {
        $this->write($text);
    }

    /** A clickable label in terminals that support OSC 8, the plain URL everywhere else. */
    public function link(string $url, ?string $label = null): string
    {
        $label ??= $url;

        if (! $this->face->interactive || ! $this->face->color) {
            return $url;
        }

        return "\e]8;;".$url."\e\\".$label."\e]8;;\e\\";
    }

    /**
     * Data that is already a structured document, such as an API body echoed by `unolia api`.
     * There is no table for a document, so the table face gets JSON.
     */
    public function document(mixed $data): void
    {
        if ($this->face->format === Format::Table) {
            $this->write($this->applyJq($this->json->document($this->selected($data))));

            return;
        }

        $this->writeData($data, []);
    }

    private function message(string $line): void
    {
        if ($this->face->format === Format::Table) {
            $this->stdout->writeln($line);

            return;
        }

        $this->stderr->writeln($line);
    }

    private function write(string $text): void
    {
        if ($text === '') {
            return;
        }

        $this->stdout->writeln($text, OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param  array<string, string>  $columns
     */
    private function writeData(mixed $data, array $columns): void
    {
        $rendered = match ($this->face->format) {
            Format::Json => $this->applyJq($this->json->document($this->selected($data))),
            Format::Ndjson => $this->renderNdjson($data),
            Format::Csv => $this->renderCsv($this->selected($data), $columns),
            Format::Yaml => $this->yaml->document($this->selected($data)),
            Format::Table => '',
        };

        $this->write(rtrim($rendered, "\n"));
    }

    private function renderNdjson(mixed $data): string
    {
        $selected = $this->selected($data);
        $rows = is_array($selected) && $selected !== [] && array_is_list($selected) ? $selected : [$selected];

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = $this->applyJq($this->ndjson->line($row));
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $columns
     */
    private function renderCsv(mixed $data, array $columns): string
    {
        if (! is_array($data)) {
            return $this->csv->line([Str::scalar($data, '')]);
        }

        if ($data !== [] && array_is_list($data)) {
            /** @var list<array<string, mixed>> $rows */
            $rows = array_values(array_filter($data, is_array(...)));

            return $this->csv->list($rows, $this->face->fields !== null ? [] : $columns);
        }

        /** @var array<string, mixed> $data */
        return $this->csv->record($data);
    }

    private function selected(mixed $data): mixed
    {
        if ($this->face->fields === null || ! is_array($data)) {
            return $data;
        }

        return FieldSelector::apply($data, $this->face->fields);
    }

    private function applyJq(string $json): string
    {
        if ($this->face->jq === null) {
            return $json;
        }

        return rtrim($this->jq->filter($json, $this->face->jq), "\n");
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columns
     * @param  (callable(array<string, mixed>): array<string, string>)|null  $decorate
     * @return array<string, string>
     */
    private function displayRow(array $row, array $columns, ?callable $decorate): array
    {
        $decorated = $decorate !== null ? $decorate($row) : [];
        $display = [];

        foreach (array_keys($columns) as $key) {
            $display[$key] = $decorated[$key] ?? Str::scalar($row[$key] ?? null);
        }

        return $display;
    }
}
