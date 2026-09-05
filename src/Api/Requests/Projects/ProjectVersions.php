<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Projects;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ProjectVersions extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'projects/'.$this->id.'/versions';
    }
}
