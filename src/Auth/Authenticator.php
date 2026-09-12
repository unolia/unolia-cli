<?php

declare(strict_types=1);

namespace Unolia\Cli\Auth;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Core\CurrentAuthenticated;
use Unolia\Cli\Api\Requests\Core\CurrentToken;
use Unolia\Cli\Api\Requests\Core\Logout;
use Unolia\Cli\Api\Requests\Core\RenameToken;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Runtime;

/**
 * Everything that turns a token into an entry of hosts.json: checking who it belongs to,
 * naming it in the dashboard, storing it and revoking it.
 */
final class Authenticator
{
    public function __construct(private readonly Runtime $runtime) {}

    /**
     * Log in through the browser and store what came back.
     *
     * @param  list<string>|null  $requestedScopes  null means the host's defaults
     * @param  array{client_id: string, device_authorization_endpoint: string, token_endpoint: string, verification_uri: string, scopes: list<string>, default_scopes: list<string>}|null  $discovery  when the caller fetched it already
     * @return array<string, mixed> the identity
     */
    public function loginWithDevice(string $host, ?array $requestedScopes, ?string $tokenName, bool $openBrowser = true, ?array $discovery = null): array
    {
        $flow = $this->runtime->get(DeviceFlow::class);
        $discovery ??= $flow->discover($host);
        $scopes = $flow->scopes($host, $discovery, $requestedScopes);
        $grant = $flow->authorize($host, $discovery, $scopes, $openBrowser);

        return $this->store($host, $grant['access_token'], $grant, $tokenName ?? $this->defaultTokenName());
    }

    /**
     * Store a token someone pasted or piped in. It keeps the name it has.
     *
     * @return array<string, mixed> the identity
     */
    public function loginWithToken(string $host, string $token): array
    {
        return $this->store($host, $token, [], null);
    }

    /**
     * Who a token belongs to and what it may do. A 401 becomes an auth error.
     *
     * @return array<string, mixed>
     */
    public function identity(string $host, string $token): array
    {
        $client = $this->runtime->clients()->make($host, $token);

        try {
            $tokenData = $client->send(new CurrentToken)->json('data');
            $principal = $client->send(new CurrentAuthenticated)->json('data');
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                throw CliError::auth('that token was rejected by '.$host, 'Run unolia login to sign in through the browser, or create a token at https://'.$host.'/user/api-tokens/create');
            }

            throw $exception;
        }

        $tokenData = is_array($tokenData) ? $tokenData : [];
        $principal = is_array($principal) ? $principal : [];

        $kind = $tokenData['tokenable_type'] ?? 'unknown';

        return [
            'host' => $host,
            'kind' => is_string($kind) ? $kind : 'unknown',
            'id' => $principal['id'] ?? null,
            'name' => $principal['name'] ?? 'unknown',
            'email' => $principal['email'] ?? null,
            'token_name' => $tokenData['name'] ?? null,
            'scopes' => self::scopeList($tokenData['scopes'] ?? null),
            'expires_at' => $tokenData['expires_at'] ?? null,
        ];
    }

    /** Revoke a token on the server. False when the server would not, which is never fatal. */
    public function revoke(string $host, string $token): bool
    {
        try {
            $this->runtime->clients()->auth($host, $token)->send(new Logout);
        } catch (ApiException) {
            return false;
        }

        return true;
    }

    /** The name a token minted here gets: who and where, the way ssh keys are named. */
    public function defaultTokenName(): string
    {
        return sprintf('%s@%s', get_current_user() ?: 'cli', gethostname() ?: 'localhost');
    }

    /**
     * The one line under "Logged in": what the token may do, unless it may do everything.
     *
     * @param  array<string, mixed>  $identity
     */
    public static function abilities(array $identity): ?string
    {
        $scopes = self::scopeList($identity['scopes'] ?? null);

        if ($scopes === [] || $scopes === ['*']) {
            return null;
        }

        return 'Abilities: '.implode(', ', $scopes);
    }

    /**
     * @param  array{expires_at?: string|null, client_id?: string}  $grant
     * @return array<string, mixed>
     */
    private function store(string $host, string $token, array $grant, ?string $tokenName): array
    {
        $identity = $this->identity($host, $token);

        if ($tokenName !== null && $this->rename($host, $token, $tokenName)) {
            $identity['token_name'] = $tokenName;
        }

        $this->runtime->hosts()->putEntry($host, [
            'token' => $token,
            'kind' => $identity['kind'],
            'name' => $identity['name'],
            'token_name' => $identity['token_name'],
            'expires_at' => $grant['expires_at'] ?? $identity['expires_at'],
            'scopes' => $identity['scopes'],
            'client_id' => $grant['client_id'] ?? null,
        ]);

        return $identity;
    }

    private function rename(string $host, string $token, string $name): bool
    {
        try {
            $this->runtime->clients()->make($host, $token)->send(new RenameToken($name));
        } catch (ApiException $exception) {
            $this->runtime->out()->warn(sprintf('The token could not be named %s (%s). It keeps its current name.', $name, $exception->message('the API refused')));

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function scopeList(mixed $scopes): array
    {
        $list = [];

        foreach (is_array($scopes) ? $scopes : [] as $scope) {
            if (is_string($scope) && $scope !== '') {
                $list[] = $scope;
            }
        }

        return $list;
    }
}
