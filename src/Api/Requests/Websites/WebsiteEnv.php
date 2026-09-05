<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Websites;

use Unolia\Cli\Api\Requests\ResourceRequest;

/**
 * Live credentials. Only ever called when the user asked for them.
 */
final class WebsiteEnv extends ResourceRequest
{
    public function resolveEndpoint(): string
    {
        return 'websites/'.$this->id.'/env';
    }
}
