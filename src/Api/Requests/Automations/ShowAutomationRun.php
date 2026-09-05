<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Automations;

use Unolia\Cli\Api\Requests\ResourceRequest;

final class ShowAutomationRun extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'automation-runs/'.$this->id;
    }
}
