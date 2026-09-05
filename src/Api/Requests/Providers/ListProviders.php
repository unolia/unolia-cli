<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Providers;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListProviders extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'providers';
    }
}
