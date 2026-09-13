<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

/**
 * Where a list stands in its pages: what is shown, what there is, and how to
 * see the rest. Read from the API's meta, said under the table.
 */
final readonly class Page
{
    public function __construct(
        public int $shown,
        public int $total,
        public int $current,
        public int $last,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function fromMeta(array $meta, int $shown): ?self
    {
        if (! is_numeric($meta['total'] ?? null) || ! is_numeric($meta['last_page'] ?? null)) {
            return null;
        }

        return new self($shown, (int) $meta['total'], (int) ($meta['current_page'] ?? 1), (int) $meta['last_page']);
    }

    public function more(): bool
    {
        return $this->last > 1;
    }

    /**
     * The footer once there is more than one page: "30 of 128 repositories ·
     * page 1 of 5 · --page 2 for the next, --paginate for all". $summary is
     * the table's own footer for the total, "128 repositories".
     */
    public function note(?string $summary): string
    {
        $parts = [
            $summary === null ? sprintf('%d of %d', $this->shown, $this->total) : sprintf('%d of %s', $this->shown, $summary),
            sprintf('page %d of %d', $this->current, $this->last),
        ];

        $ways = [];

        if ($this->current < $this->last) {
            $ways[] = sprintf('--page %d for the next', $this->current + 1);
        }

        $ways[] = 'all: --paginate';
        $parts[] = implode(', ', $ways);

        return implode(' · ', $parts);
    }
}
