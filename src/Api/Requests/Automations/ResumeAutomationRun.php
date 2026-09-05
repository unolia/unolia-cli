<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Automations;

use Unolia\Cli\Api\Requests\MutationRequest;

final class ResumeAutomationRun extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'automation-runs/'.$this->id.'/resume';
    }
}
