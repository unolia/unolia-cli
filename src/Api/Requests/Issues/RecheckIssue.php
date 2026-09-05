<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Issues;

use Unolia\Cli\Api\Requests\MutationRequest;

final class RecheckIssue extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'issues/'.$this->id.'/recheck';
    }
}
