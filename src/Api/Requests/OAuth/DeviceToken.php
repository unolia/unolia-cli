<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\OAuth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * POST the token endpoint with the device code grant (RFC 8628 section 3.4). Answers
 * authorization_pending until the person approves the code in the browser.
 */
final class DeviceToken extends Request implements HasBody
{
    use HasFormBody;

    public const GRANT = 'urn:ietf:params:oauth:grant-type:device_code';

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $clientId,
        private readonly string $deviceCode,
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
            'grant_type' => self::GRANT,
            'device_code' => $this->deviceCode,
            'client_id' => $this->clientId,
        ];
    }
}
