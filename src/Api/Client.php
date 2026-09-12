<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\Traits\Plugins\AcceptsJson;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Version;

/**
 * The connector for /api/v1. Error mapping, the team header and debug logging live here
 * so no command ever has to think about them.
 */
final class Client extends Connector implements HasPagination
{
    use AcceptsJson;

    /** @var (callable(): ?string)|null */
    private $teamResolver = null;

    /** @var (callable(string): void)|null */
    private $debugLogger = null;

    public function __construct(
        private readonly string $host = 'app.unolia.com',
        private readonly ?string $token = null,
        private readonly string $tokenKind = 'unknown',
        private readonly bool $insecure = false,
        private readonly string $basePath = 'api/v1/',
        private readonly bool $verify = true,
    ) {}

    /** @param callable(): ?string $resolver */
    public function resolveTeamUsing(callable $resolver): void
    {
        $this->teamResolver = $resolver;
    }

    /** @param callable(string): void $logger */
    public function logDebugUsing(callable $logger): void
    {
        $this->debugLogger = $logger;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function hasToken(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    public function webUrl(string $path = ''): string
    {
        return $this->scheme().'://'.$this->host.'/'.ltrim($path, '/');
    }

    public function resolveBaseUrl(): string
    {
        return $this->scheme().'://'.$this->host.'/'.$this->basePath;
    }

    public function paginate(Request $request): Paginator
    {
        return new Paginator(connector: $this, request: $request);
    }

    /**
     * @throws ApiException
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        if (! $this->hasToken()) {
            throw CliError::auth('you are not logged in');
        }

        $started = microtime(true);
        $method = $request->getMethod()->value;
        $path = $request->resolveEndpoint();

        try {
            $response = parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $exception) {
            $this->log(sprintf('→ %s /v1/%s failed: %s', $method, ltrim($path, '/'), $exception->getMessage()));

            throw ApiException::network($this->host, $exception->getMessage());
        }

        $this->log(sprintf(
            '→ %s /v1/%s %d %dms',
            $method,
            ltrim($path, '/'),
            $response->status(),
            (int) round((microtime(true) - $started) * 1000),
        ));

        if ($response->failed()) {
            throw ApiException::fromResponse($response, $method, $path, $this->host);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = ['User-Agent' => Version::userAgent()];

        if ($this->tokenKind !== 'team' && $this->teamResolver !== null) {
            $team = ($this->teamResolver)();

            if (is_string($team) && $team !== '') {
                $headers['X-Unolia-Team'] = $team;
            }
        }

        return $headers;
    }

    protected function defaultAuth(): ?TokenAuthenticator
    {
        return $this->hasToken() ? new TokenAuthenticator((string) $this->token) : null;
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

    protected function scheme(): string
    {
        return $this->insecure ? 'http' : 'https';
    }

    private function log(string $line): void
    {
        if ($this->debugLogger !== null) {
            ($this->debugLogger)($line);
        }
    }
}
