<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Unolia\Cli\Api\Requests\NestedPaginatedRequest;

final class ListDomainRecords extends NestedPaginatedRequest
{
    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->id.'/records';
    }
}
