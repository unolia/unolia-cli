<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Deployments;

use Unolia\Cli\Api\Requests\ResourceRequest;

/**
 * Long polls with wait=N so a watch makes three requests a minute instead of twenty.
 */
final class ShowDeployment extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'deployments/'.$this->id;
    }
}
