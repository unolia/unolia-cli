<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Websites;

use Unolia\Cli\Api\Requests\MutationRequest;

final class CreateDeployment extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'websites/'.$this->id.'/deployments';
    }
}
