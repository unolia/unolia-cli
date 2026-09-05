<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Projects;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListProjects extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'projects';
    }
}
