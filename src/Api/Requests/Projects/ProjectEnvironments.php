<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Projects;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ProjectEnvironments extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'projects/'.$this->id.'/environments';
    }
}
