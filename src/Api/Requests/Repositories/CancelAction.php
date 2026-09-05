<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\MutationRequest;

final class CancelAction extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'actions/'.$this->id.'/cancel';
    }
}
