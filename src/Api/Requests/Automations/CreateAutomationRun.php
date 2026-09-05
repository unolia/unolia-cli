<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Automations;

use Unolia\Cli\Api\Requests\MutationRequest;

final class CreateAutomationRun extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'automations/'.$this->id.'/runs';
    }
}
