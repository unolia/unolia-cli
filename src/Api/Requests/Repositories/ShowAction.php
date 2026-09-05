<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowAction extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'actions/'.$this->id;
    }
}
