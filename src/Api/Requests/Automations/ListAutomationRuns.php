<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Automations;

use Unolia\Cli\Api\Requests\PaginatedRequest;

final class ListAutomationRuns extends PaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'automation-runs';
    }
}
