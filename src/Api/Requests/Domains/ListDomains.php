<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListDomains extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'domains';
    }
}
