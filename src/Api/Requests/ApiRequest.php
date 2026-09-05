<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Unolia\Cli\Support\Arr;

/**
 * Shared plumbing for every /api/v1 request: filters become query parameters, and a
 * request that long polls with wait=N is given the matching socket timeout.
 */
abstract class ApiRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected readonly array $filters = []) {}

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return Arr::filled($this->filters);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        $wait = $this->filters['wait'] ?? null;

        return is_numeric($wait) && (int) $wait > 0 ? ['timeout' => (int) $wait + 10] : [];
    }
}
