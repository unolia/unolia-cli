<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Websites;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListWebsites extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'websites';
    }
}
