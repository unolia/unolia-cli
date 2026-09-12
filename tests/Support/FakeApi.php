<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use RuntimeException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Unolia\Cli\Api\AuthClient;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\OAuthClient;

/**
 * Canned API answers, matched on method and path. Every registered answer must be used,
 * so a command that stops calling an endpoint fails the test loudly.
 */
final class FakeApi
{
    /** @var list<array{method: string, path: string, query: array<string, string>, status: int, body: mixed, optional: bool, used: bool}> */
    private array $routes = [];

    /** @var list<array{method: string, path: string, query: array<string, mixed>, body: mixed}> */
    private array $calls = [];

    /** Set when a command asked for something the test never registered. */
    private ?string $failure = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<mixed>|string  $body
     */
    public function on(string $method, string $path, array|string $body = [], int $status = 200): self
    {
        [$path, $query] = $this->split($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => trim($path, '/'),
            'query' => $query,
            'status' => $status,
            'body' => $body,
            'optional' => false,
            'used' => false,
        ];

        return $this;
    }

    /** Mark the last registered answer as one the command may skip. */
    public function optional(): self
    {
        $last = array_key_last($this->routes);

        if ($last !== null) {
            $this->routes[$last]['optional'] = true;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public static function fixture(string $name): array
    {
        $path = __DIR__.'/../Fixtures/api/'.$name;

        if (! is_file($path)) {
            Assert::fail(sprintf('The fixture %s does not exist.', $name));
        }

        /** @var array<mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true);

        return $decoded;
    }

    public function mockClient(): MockClient
    {
        $handler = fn (PendingRequest $pending): MockResponse => $this->answer($pending);

        return new MockClient([
            Client::class => $handler,
            AuthClient::class => $handler,
            OAuthClient::class => $handler,
        ]);
    }

    /**
     * @return list<array{method: string, path: string, query: array<string, mixed>, body: mixed}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return array{method: string, path: string, query: array<string, mixed>, body: mixed}|null
     */
    public function lastCall(): ?array
    {
        return $this->calls === [] ? null : $this->calls[array_key_last($this->calls)];
    }

    public function assertEverythingUsed(): void
    {
        foreach ($this->routes as $route) {
            if (! $route['used'] && ! $route['optional']) {
                Assert::fail(sprintf('The API answer for %s %s was never used.', $route['method'], $route['path']));
            }
        }
    }

    private function answer(PendingRequest $pending): MockResponse
    {
        $method = $pending->getMethod()->value;
        $url = $pending->getUrl();
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $path = preg_replace('#^api/#', '', $path) ?? $path;
        $query = $pending->query()->all();

        $this->calls[] = [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'body' => $this->decodeBody($pending),
        ];

        foreach ($this->routes as $index => $route) {
            if ($route['used'] || $route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }

            if (! $this->queryMatches($route['query'], $query)) {
                continue;
            }

            $this->routes[$index]['used'] = true;

            return MockResponse::make($route['body'], $route['status']);
        }

        $this->failure ??= sprintf(
            'No API answer registered for %s %s%s',
            $method,
            $path,
            $query === [] ? '' : '?'.http_build_query($query),
        );

        throw new RuntimeException($this->failure);
    }

    /** The first unregistered request, reported by the tester once the run is over. */
    public function failure(): ?string
    {
        return $this->failure;
    }

    /**
     * @param  array<string, string>  $expected
     * @param  array<string, mixed>  $actual
     */
    private function queryMatches(array $expected, array $actual): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual) || (string) $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function split(string $path): array
    {
        if (! str_contains($path, '?')) {
            return [$path, []];
        }

        [$path, $queryString] = explode('?', $path, 2);
        parse_str($queryString, $parsed);

        $query = [];

        foreach ($parsed as $key => $value) {
            $query[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return [$path, $query];
    }

    private function decodeBody(PendingRequest $pending): mixed
    {
        $body = $pending->body();

        if ($body === null) {
            return null;
        }

        $contents = $body->all();

        if (is_array($contents)) {
            return $contents;
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) $body, true);

        return $decoded ?? (string) $body;
    }
}
