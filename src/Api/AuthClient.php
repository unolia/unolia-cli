<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Unolia\Cli\Version;

/**
 * The connector for /api, which is where login and logout still live.
 */
final class AuthClient extends Connector
{
    use AcceptsJson;

    public function __construct(
        private readonly string $host = 'app.unolia.com',
        private readonly ?string $token = null,
        private readonly bool $insecure = false,
    ) {}

    public function resolveBaseUrl(): string
    {
        return ($this->insecure ? 'http' : 'https').'://'.$this->host.'/api/';
    }

    /**
     * @throws ApiException
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $method = $request->getMethod()->value;
        $path = $request->resolveEndpoint();

        try {
            $response = parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $exception) {
            throw ApiException::network($this->host, $exception->getMessage());
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($response, $method, $path);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => Version::userAgent()];
    }

    protected function defaultAuth(): ?TokenAuthenticator
    {
        return $this->token !== null && $this->token !== '' ? new TokenAuthenticator($this->token) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 10,
        ];
    }
}
