<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\OAuth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * POST the device authorization endpoint (RFC 8628 section 3.1): asks for a user code.
 */
final class DeviceAuthorization extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly string $clientId,
        private readonly array $scopes,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'client_id' => $this->clientId,
            'scope' => implode(' ', $this->scopes),
        ];
    }
}
