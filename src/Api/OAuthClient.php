<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Unolia\Cli\Version;

/**
 * The connector rooted at the host itself, for the device code flow: discovery, the
 * device authorization and the token endpoint. It carries no token, and a 4xx is a
 * normal answer here because RFC 8628 says "authorization_pending" with a 400.
 */
final class OAuthClient extends Connector
{
    use AcceptsJson;

    public function __construct(
        private readonly string $host = 'app.unolia.com',
        private readonly bool $insecure = false,
        private readonly bool $verify = true,
    ) {}

    public function host(): string
    {
        return $this->host;
    }

    public function resolveBaseUrl(): string
    {
        return ($this->insecure ? 'http' : 'https').'://'.$this->host.'/';
    }

    /**
     * The response, whatever its status. Only a connection failure throws.
     *
     * @throws ApiException
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $exception) {
            throw ApiException::network($this->host, $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => Version::userAgent()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => $this->verify,
        ];
    }
}
