<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Websites;

use Unolia\Cli\Api\Requests\NestedPaginatedRequest;

final class ListWebsiteDeployments extends NestedPaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'websites/'.$this->id.'/deployments';
    }
}
