<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\PagedPaginator;

/**
 * Every list answers with data, links and meta. --paginate walks them all.
 */
final class Paginator extends PagedPaginator
{
    protected function isLastPage(Response $response): bool
    {
        $next = $response->json('links.next');

        if ($next !== null) {
            return false;
        }

        $last = $response->json('meta.last_page');

        if (is_numeric($last)) {
            return $this->getCurrentPage() >= (int) $last;
        }

        return true;
    }

    /**
     * @return array<mixed>
     */
    protected function getPageItems(Response $response, Request $request): array
    {
        $items = $response->json('data');

        return is_array($items) ? $items : [];
    }

    protected function getTotalPages(Response $response): int
    {
        $last = $response->json('meta.last_page');

        return is_numeric($last) ? (int) $last : 1;
    }

    /**
     * Every row of every page, as plain arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->items() as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
        }

        return $rows;
    }
}
