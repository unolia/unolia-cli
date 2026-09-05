<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Automations;

use Unolia\Cli\Api\Requests\MutationRequest;

final class ReplayAutomationRun extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'automation-runs/'.$this->id.'/replay';
    }
}
