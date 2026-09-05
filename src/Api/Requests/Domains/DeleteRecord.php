<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Saloon\Enums\Method;
use Unolia\Cli\Api\Requests\ResourceRequest;

final class DeleteRecord extends ResourceRequest
{
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return 'records/'.$this->id;
    }
}
