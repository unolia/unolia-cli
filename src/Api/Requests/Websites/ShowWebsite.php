<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Websites;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowWebsite extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'websites/'.$this->id;
    }
}
