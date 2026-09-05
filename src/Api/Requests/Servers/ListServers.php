<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Servers;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListServers extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'servers';
    }
}
