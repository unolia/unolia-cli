<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Deployments;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListDeployments extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'deployments';
    }
}
