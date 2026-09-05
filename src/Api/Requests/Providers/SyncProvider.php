<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Providers;

use Unolia\Cli\Api\Requests\MutationRequest;

final class SyncProvider extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'providers/'.$this->id.'/sync';
    }
}
