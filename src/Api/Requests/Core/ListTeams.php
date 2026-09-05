<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListTeams extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'teams';
    }
}
