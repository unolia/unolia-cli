<?php

namespace App\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DomainsRecordsShow extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $domain,
        protected readonly string $record,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->domain.'/records/'.$this->record;
    }
}
