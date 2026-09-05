<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Providers;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowProvider extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'providers/'.$this->id;
    }
}
