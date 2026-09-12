<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

use Closure;
use Unolia\Cli\Support\Str;

/**
 * A column of the table face: which key of the row it shows, under which
 * header, aligned which way, and how the raw value becomes a cell.
 */
final class Column
{
    /** @var Closure(array<string, mixed>): (Cell|string|null) */
    private Closure $cell;

    private function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly Align $align = Align::Left,
        ?Closure $cell = null,
    ) {
        $this->cell = $cell ?? fn (array $row): Cell => Cell::text(Str::scalar($row[$this->key] ?? null));
    }

    public static function make(string $key, string $header = ''): self
    {
        return new self($key, $header);
    }

    public function right(): self
    {
        return new self($this->key, $this->header, Align::Right, $this->cell);
    }

    /**
     * @param  Closure(array<string, mixed>): (Cell|string|null)  $cell
     */
    public function cell(Closure $cell): self
    {
        return new self($this->key, $this->header, $this->align, $cell);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function render(array $row): Cell
    {
        $value = ($this->cell)($row);

        return match (true) {
            $value instanceof Cell => $value,
            $value === null => Cell::empty(),
            default => Cell::text($value),
        };
    }
}
