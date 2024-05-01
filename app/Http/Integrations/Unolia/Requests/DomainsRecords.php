<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class DomainsRecords extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $domain
    ) {
    }

    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->domain.'/records';
    }
}
