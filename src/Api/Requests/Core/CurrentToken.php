<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Unolia\Cli\Api\Requests\ApiRequest;

final class CurrentToken extends ApiRequest
{
    public function resolveEndpoint(): string
    {
        return 'current/token';
    }
}
