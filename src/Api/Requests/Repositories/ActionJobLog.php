<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Repositories;

use Unolia\Cli\Api\Requests\ApiRequest;

final class ActionJobLog extends ApiRequest
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int|string $action,
        private readonly int|string $job,
        array $filters = [],
    ) {
        parent::__construct($filters);
    }

    public function resolveEndpoint(): string
    {
        return 'actions/'.$this->action.'/jobs/'.$this->job.'/log';
    }
}
