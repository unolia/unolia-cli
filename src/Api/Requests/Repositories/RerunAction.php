<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\MutationRequest;

final class RerunAction extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'actions/'.$this->id.'/rerun';
    }
}
