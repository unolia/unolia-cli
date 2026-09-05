<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Incidents;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListIncidents extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'incidents';
    }
}
