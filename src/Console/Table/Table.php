<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

use Closure;

/**
 * What a list looks like on every face. The columns draw the table face; the
 * fields name what the data faces (json, csv, yaml, ndjson) carry, which may
 * be more than the table shows. Rows can be sorted for the table face only,
 * and a footer can sum them up.
 */
final class Table
{
    /** @var list<Column> */
    public readonly array $columns;

    /** @var array<string, string> */
    private array $fields;

    /** @var (Closure(array<string, mixed>, array<string, mixed>): int)|null */
    private ?Closure $sort = null;

    /** @var (Closure(int): string)|null */
    private ?Closure $footer = null;

    private function __construct(Column ...$columns)
    {
        $this->columns = array_values($columns);
        $fields = [];

        foreach ($this->columns as $column) {
            if ($column->header !== '') {
                $fields[$column->key] = $column->header;
            }
        }

        $this->fields = $fields;
    }

    public static function make(Column ...$columns): self
    {
        return new self(...$columns);
    }

    /**
     * The keys the data faces carry, key => label. Defaults to the columns with a header.
     *
     * @param  array<string, string>  $fields
     */
    public function fields(array $fields): self
    {
        $clone = clone $this;
        $clone->fields = $fields;

        return $clone;
    }

    /**
     * @param  Closure(array<string, mixed>, array<string, mixed>): int  $sort
     */
    public function sort(Closure $sort): self
    {
        $clone = clone $this;
        $clone->sort = $sort;

        return $clone;
    }

    /**
     * @param  Closure(int): string  $footer  given the row count
     */
    public function footer(Closure $footer): self
    {
        $clone = clone $this;
        $clone->footer = $footer;

        return $clone;
    }

    /** @return array<string, string> */
    public function dataFields(): array
    {
        return $this->fields;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function order(array $rows): array
    {
        if ($this->sort !== null) {
            usort($rows, $this->sort);
        }

        return $rows;
    }

    public function summary(int $count): ?string
    {
        return $this->footer === null ? null : ($this->footer)($count);
    }
}
