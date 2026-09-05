<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Saloon\Enums\Method;
use Unolia\Cli\Api\Requests\MutationRequest;

final class UpdateRecord extends MutationRequest
{
    protected Method $method = Method::PATCH;

    public function resolveEndpoint(): string
    {
        return 'records/'.$this->id;
    }
}
