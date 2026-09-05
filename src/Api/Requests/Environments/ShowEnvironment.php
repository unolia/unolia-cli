<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Environments;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowEnvironment extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'environments/'.$this->id;
    }
}
