<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Issues;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListIssues extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'issues';
    }
}
