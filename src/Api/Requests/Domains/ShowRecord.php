<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowRecord extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'records/'.$this->id;
    }
}
