<?php

namespace App\Http\Integrations\Unolia\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DomainsRecordsUpdate extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected readonly int $record,
        protected readonly string $name,
        protected readonly string $value,
        protected readonly ?int $ttl = null,
    ) {
    }

    public function defaultBody(): array
    {
        return array_filter([
            'name' => $this->name,
            'value' => $this->value,
            'ttl' => $this->ttl,
        ]);
    }

    public function resolveEndpoint(): string
    {
        return 'records/'.$this->record;
    }
}
