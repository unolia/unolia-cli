<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Issues;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowIssue extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'issues/'.$this->id;
    }
}
