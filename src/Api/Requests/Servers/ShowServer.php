<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Servers;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowServer extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'servers/'.$this->id;
    }
}
