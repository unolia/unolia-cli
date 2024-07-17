<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DomainsRecordsDelete extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $record,
    ) {}

    public function resolveEndpoint(): string
    {
        return 'records/'.$this->record;
    }
}
