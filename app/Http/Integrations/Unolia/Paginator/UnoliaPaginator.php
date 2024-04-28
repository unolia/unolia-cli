<?php

namespace App\Http\Integrations\Unolia\Paginator;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\PagedPaginator;

class UnoliaPaginator extends PagedPaginator
{
    protected function isLastPage(Response $response): bool
    {
        return $this->currentPage == $this->getTotalPages($response);
    }

    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('data');
    }

    protected function getTotalPages(Response $response): int
    {
        return $response->json('meta.last_page');
    }
}
