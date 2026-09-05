<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowDomain extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->id;
    }
}
