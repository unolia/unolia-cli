<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Unolia\Cli\Api\Requests\ApiRequest;

/**
 * GET v1/resolve?remote=… answers what a git remote maps to.
 */
final class Resolve extends ApiRequest
{
    public function resolveEndpoint(): string
    {
        return 'resolve';
    }
}
