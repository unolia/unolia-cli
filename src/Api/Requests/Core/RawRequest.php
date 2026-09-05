<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\Traits\Body\HasStringBody;

/**
 * Whatever `unolia api` was asked for. Method, path, query, headers and body all come
 * from the command line, the way gh api works.
 */
final class RawRequest extends Request implements HasBody, Paginatable
{
    use HasStringBody;

    protected Method $method;

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        Method $method,
        private readonly string $path,
        private readonly array $parameters = [],
        private readonly array $extraHeaders = [],
        private readonly ?string $rawBody = null,
    ) {
        $this->method = $method;
    }

    public function resolveEndpoint(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return $this->rawBody !== null
            ? array_merge(['Content-Type' => 'application/json'], $this->extraHeaders)
            : $this->extraHeaders;
    }

    protected function defaultBody(): ?string
    {
        return $this->rawBody;
    }
}
