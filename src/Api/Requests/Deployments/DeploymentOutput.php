<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Deployments;

use Unolia\Cli\Api\Requests\ResourceRequest;

/**
 * The deployment log, read with a byte cursor: after=N, wait=M.
 */
final class DeploymentOutput extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'deployments/'.$this->id.'/output';
    }
}
