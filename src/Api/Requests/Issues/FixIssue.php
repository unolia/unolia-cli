<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Issues;

use Unolia\Cli\Api\Requests\MutationRequest;

final class FixIssue extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'issues/'.$this->id.'/fix';
    }
}
