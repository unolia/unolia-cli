<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * PATCH /api/v2/current/token: give the token in use a name people recognise in the dashboard.
 */
final class RenameToken extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(private readonly string $name) {}

    public function resolveEndpoint(): string
    {
        return 'current/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['name' => $this->name];
    }
}
