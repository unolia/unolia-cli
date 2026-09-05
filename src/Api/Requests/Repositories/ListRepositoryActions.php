<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\NestedPaginatedRequest;

final class ListRepositoryActions extends NestedPaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'repositories/'.$this->id.'/actions';
    }
}
