<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DomainsRecordsCreate extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected readonly string $domain,
        protected readonly string $name,
        protected readonly string $type,
        protected readonly string $value,
        protected readonly ?int $ttl = null,
    ) {
    }

    public function defaultBody(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'value' => $this->value,
            'ttl' => $this->ttl,
        ]);
    }

    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->domain.'/records';
    }
}
