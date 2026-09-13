<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Table\Align;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Page;
use Unolia\Cli\Console\Table\Table;

/**
 * The table face without a box: a dim uppercase header, cells aligned on the
 * plain text width, two spaces between columns, a dim footer. On a terminal
 * the whole block sits two spaces in from the left edge; a pipe with
 * --format table gets the same layout flush left in plain text, so grep and
 * awk still work on it.
 */
final class StyledTableRenderer
{
    /** The left margin of the terminal face. */
    public const MARGIN = '  ';

    public function __construct(private readonly Face $face) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function render(array $rows, Table $table, ?Page $page = null): string
    {
        $styled = $this->face->interactive;
        $links = $styled && $this->face->color;

        $columns = $table->columns;
        $matrix = [];

        foreach ($table->order($rows) as $row) {
            $matrix[] = array_map(static fn ($column): Cell => $column->render($row), $columns);
        }

        // On a pipe a cell may stand for something else (a glyph becomes a word,
        // or nothing), and a column left with no header and no text is dropped.
        $text = static fn (Cell $cell): string => $styled ? $cell->text : $cell->plainText();

        $widths = [];

        foreach ($columns as $index => $column) {
            $widths[$index] = mb_strwidth($column->header);

            foreach ($matrix as $cells) {
                $widths[$index] = max($widths[$index], mb_strwidth($text($cells[$index])));
            }
        }

        $shown = array_keys(array_filter($widths, static fn (int $width): bool => $width > 0));

        $header = [];

        foreach ($shown as $index) {
            $header[] = $this->pad(strtoupper($columns[$index]->header), $widths[$index], $columns[$index]->align, $styled ? '<fg=gray>%s</>' : null);
        }

        $lines = [rtrim(implode('  ', $header))];

        foreach ($matrix as $cells) {
            $parts = [];

            foreach ($shown as $index) {
                $cell = $cells[$index];
                $rendered = $styled ? $cell->styled($links) : $cell->plainText();
                $parts[] = $this->padRendered($rendered, mb_strwidth($text($cell)), $widths[$index], $columns[$index]->align);
            }

            $lines[] = rtrim(implode('  ', $parts));
        }

        // The footer counts what is shown, or, when there are more pages, says
        // how much there is and how to see the rest.
        $summary = $page !== null && $page->more()
            ? $page->note($table->summary($page->total))
            : $table->summary(count($matrix));

        if ($summary !== null) {
            $lines[] = '';
            $lines[] = $styled ? '<fg=gray>'.$summary.'</>' : $summary;
        }

        if ($styled) {
            $lines = array_map(static fn (string $line): string => $line === '' ? '' : self::MARGIN.$line, $lines);
        }

        return implode("\n", $lines);
    }

    /** Style the text, then pad around it, so the padding stays trimmable. */
    private function pad(string $text, int $width, Align $align, ?string $wrap): string
    {
        $rendered = $wrap === null || $text === '' ? $text : sprintf($wrap, $text);

        return $this->padRendered($rendered, mb_strwidth($text), $width, $align);
    }

    /** Pad a rendered string by the width of its plain text, so tags do not count. */
    private function padRendered(string $rendered, int $plainWidth, int $width, Align $align): string
    {
        $padding = max(0, $width - $plainWidth);

        return $align === Align::Right
            ? str_repeat(' ', $padding).$rendered
            : $rendered.str_repeat(' ', $padding);
    }
}
