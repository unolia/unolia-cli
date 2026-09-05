<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Incidents;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowIncident extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'incidents/'.$this->id;
    }
}
