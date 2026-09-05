<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Domains;

use Unolia\Cli\Api\Requests\MutationRequest;

final class CreateRecord extends MutationRequest
{
    public function resolveEndpoint(): string
    {
        return 'domains/'.$this->id.'/records';
    }
}
