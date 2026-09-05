<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * DELETE /api/logout, unchanged from v1.
 */
final class Logout extends Request
{
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return 'logout';
    }
}
