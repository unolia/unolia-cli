<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\OAuth;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /api/v2/cli/oauth, unauthenticated. Where the device flow endpoints are, which
 * client id the CLI is, and which scopes exist.
 */
final class Discovery extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'api/v2/cli/oauth';
    }
}
