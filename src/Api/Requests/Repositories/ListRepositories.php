<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListRepositories extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'repositories';
    }
}
