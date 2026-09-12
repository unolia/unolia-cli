<?php

declare(strict_types=1);

namespace Unolia\Cli\Auth;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\OAuthClient;
use Unolia\Cli\Api\Poller;
use Unolia\Cli\Api\Requests\OAuth\DeviceAuthorization;
use Unolia\Cli\Api\Requests\OAuth\DeviceToken;
use Unolia\Cli\Api\Requests\OAuth\Discovery;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Runtime;
use Unolia\Cli\Support\Browser;

/**
 * The device code flow of RFC 8628, the way gh logs in: show a short code, open the
 * browser, poll the token endpoint until the person approves the code there.
 *
 * Exit codes: 3 when the login is refused in the browser, 6 when the code expires
 * first, 130 on Ctrl+C. Nothing is stored here, the Authenticator does that.
 */
final class DeviceFlow
{
    /** Added to the interval every time the server answers slow_down, per the RFC. */
    private const SLOW_DOWN_STEP = 5;

    public function __construct(private readonly Runtime $runtime) {}

    /**
     * What the host publishes about its device flow.
     *
     * @return array{client_id: string, device_authorization_endpoint: string, token_endpoint: string, verification_uri: string, scopes: list<string>, default_scopes: list<string>}
     */
    public function discover(string $host): array
    {
        $response = $this->oauth($host)->send(new Discovery);

        if ($response->status() === 404) {
            throw new CliError(
                'unsupported',
                sprintf('%s does not offer the browser login', $host),
                ExitCode::RemoteFailure,
                'Create a token in the dashboard and run unolia login --with-token < token.txt.',
            );
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($response, 'GET', 'api/v1/cli/oauth', $host);
        }

        $data = $response->json('data');
        $data = is_array($data) ? $data : [];

        $clientId = $data['client_id'] ?? null;

        if (! is_string($clientId) || $clientId === '') {
            throw CliError::remoteFailure(sprintf('%s answered the discovery without a client id', $host));
        }

        return [
            'client_id' => $clientId,
            'device_authorization_endpoint' => $this->url($data['device_authorization_endpoint'] ?? null, 'oauth/device/code'),
            'token_endpoint' => $this->url($data['token_endpoint'] ?? null, 'oauth/token'),
            'verification_uri' => $this->url($data['verification_uri'] ?? null, 'https://'.$host.'/oauth/device'),
            'scopes' => $this->strings($data['scopes'] ?? null),
            'default_scopes' => $this->strings($data['default_scopes'] ?? null),
        ];
    }

    /**
     * The scopes to ask for: what was requested, checked against what exists, or the
     * host's defaults. `*` is refused, since the device grant cannot issue it.
     *
     * @param  array{scopes: list<string>, default_scopes: list<string>}  $discovery
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public function scopes(string $host, array $discovery, ?array $requested): array
    {
        if ($requested === null || $requested === []) {
            if ($discovery['default_scopes'] === []) {
                throw CliError::remoteFailure(
                    sprintf('%s offered no default scopes for the browser login', $host),
                    'Pass --scopes <list> to choose them.',
                );
            }

            return $discovery['default_scopes'];
        }

        self::rejectWildcard($host, $requested);

        $known = $discovery['scopes'];
        $unknown = $known === [] ? [] : array_values(array_diff($requested, $known));

        if ($unknown !== []) {
            throw CliError::usage(
                sprintf('unknown scope%s %s', count($unknown) === 1 ? '' : 's', implode(', ', $unknown)),
                'Known scopes: '.implode(', ', $known),
                ['unknown' => $unknown, 'candidates' => $known],
            );
        }

        return array_values(array_unique($requested));
    }

    /**
     * A token that may do everything only comes from the dashboard. The device grant
     * would silently strip `*` and hand back a token that may do nothing.
     *
     * @param  list<string>  $scopes
     */
    public static function rejectWildcard(string $host, array $scopes): void
    {
        if (! in_array('*', $scopes, true)) {
            return;
        }

        throw CliError::usage(
            'the browser login cannot grant *',
            sprintf('Full-access tokens are created on the dashboard at https://%s/user/api-tokens/create and stored with unolia login --token', $host),
            ['candidates' => []],
        );
    }

    /**
     * Run the flow end to end and hand back the grant.
     *
     * @param  array{client_id: string, device_authorization_endpoint: string, token_endpoint: string, verification_uri: string}  $discovery
     * @param  list<string>  $scopes
     * @param  ?string  $replaces  id of the token being superseded, shown as a diff on the consent page
     * @return array{access_token: string, expires_at: string|null, client_id: string}
     */
    public function authorize(string $host, array $discovery, array $scopes, bool $openBrowser = true, ?string $replaces = null): array
    {
        $oauth = $this->oauth($host);
        $code = $this->requestCode($oauth, $host, $discovery, $scopes);

        if ($replaces !== null && $replaces !== '') {
            // Straight to the consent page, telling it which token this one
            // supersedes so it can show what changes instead of everything.
            $code['verification_uri_complete'] = sprintf(
                '%s/authorize?user_code=%s&replaces=%s',
                rtrim($code['verification_uri'], '/'),
                rawurlencode($code['user_code']),
                rawurlencode($replaces),
            );
        }

        $this->present($code, $openBrowser);

        $grant = $this->poll($oauth, $host, $discovery, $code);
        $expiresIn = $grant['expires_in'] ?? null;

        return [
            'access_token' => $grant['access_token'],
            'expires_at' => is_numeric($expiresIn) ? gmdate('c', time() + (int) $expiresIn) : null,
            'client_id' => $discovery['client_id'],
        ];
    }

    /**
     * @param  array{client_id: string, device_authorization_endpoint: string}  $discovery
     * @param  list<string>  $scopes
     * @return array{device_code: string, user_code: string, verification_uri: string, verification_uri_complete: string, expires_in: int, interval: int}
     */
    private function requestCode(OAuthClient $oauth, string $host, array $discovery, array $scopes): array
    {
        $endpoint = $this->path($discovery['device_authorization_endpoint']);
        $response = $oauth->send(new DeviceAuthorization($endpoint, $discovery['client_id'], $scopes));

        if ($response->failed()) {
            $exception = ApiException::fromResponse($response, 'POST', $endpoint, $host);

            if ($exception->errorCode() === 'invalid_scope') {
                throw CliError::usage($exception->message('the host refused those scopes'), 'Run unolia login --help to see how scopes are passed.');
            }

            throw $exception;
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $deviceCode = $body['device_code'] ?? null;
        $userCode = $body['user_code'] ?? null;

        if (! is_string($deviceCode) || $deviceCode === '' || ! is_string($userCode) || $userCode === '') {
            throw CliError::remoteFailure(sprintf('%s answered without a device code', $host));
        }

        $verificationUri = $this->url($body['verification_uri'] ?? null, 'https://'.$host.'/oauth/device');
        $complete = $this->url($body['verification_uri_complete'] ?? null, $verificationUri.'?user_code='.rawurlencode($userCode));
        $expiresIn = $body['expires_in'] ?? null;
        $interval = $body['interval'] ?? null;

        return [
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'verification_uri' => $verificationUri,
            'verification_uri_complete' => $complete,
            'expires_in' => is_numeric($expiresIn) ? (int) $expiresIn : 900,
            'interval' => is_numeric($interval) ? (int) $interval : 5,
        ];
    }

    /**
     * The GitHub shaped prompt: the code first, then the browser.
     *
     * @param  array{user_code: string, verification_uri: string, verification_uri_complete: string}  $code
     */
    private function present(array $code, bool $openBrowser): void
    {
        $out = $this->runtime->out();

        $out->line('First copy your one-time code: '.$code['user_code']);

        if ($openBrowser) {
            $openBrowser = $this->runtime->ask()->confirm(sprintf('Press Enter to open %s in your browser', $code['verification_uri']));
        }

        $opened = $openBrowser && $this->runtime->get(Browser::class)->open($code['verification_uri_complete'], $this->browserOverride());

        if (! $opened) {
            $out->note(sprintf('Open %s and enter the code.', $code['verification_uri']));
        }

        $out->note('Waiting for you to approve the code in the browser...');
    }

    /**
     * @param  array{client_id: string, token_endpoint: string}  $discovery
     * @param  array{device_code: string, expires_in: int, interval: int}  $code
     * @return array<string, mixed>&array{access_token: string}
     */
    private function poll(OAuthClient $oauth, string $host, array $discovery, array $code): array
    {
        $endpoint = $this->path($discovery['token_endpoint']);
        $request = new DeviceToken($endpoint, $discovery['client_id'], $code['device_code']);
        $interval = max($code['interval'], Poller::MINIMUM_INTERVAL);

        $fetch = function () use ($oauth, $host, $endpoint, $request, &$interval): array {
            $response = $oauth->send($request);
            $body = $response->json();
            $body = is_array($body) ? $body : [];
            $error = $body['error'] ?? null;
            $error = is_string($error) ? $error : null;

            if ($response->successful() && is_string($body['access_token'] ?? null) && $body['access_token'] !== '') {
                return ['done' => true, 'grant' => $body];
            }

            switch ($error) {
                case 'authorization_pending':
                    break;
                case 'slow_down':
                    $interval += self::SLOW_DOWN_STEP;
                    break;
                case 'access_denied':
                    throw CliError::auth('the login was refused in the browser', 'Run unolia login to try again.');
                case 'expired_token':
                    throw self::expired();
                default:
                    throw ApiException::fromResponse($response, 'POST', $endpoint, $host);
            }

            return ['done' => false, 'meta' => ['poll_interval' => $interval]];
        };

        try {
            $state = $this->runtime->poller()->until(
                $fetch,
                static fn (array $state): bool => $state['done'] === true,
                $interval,
                $code['expires_in'],
            );
        } catch (CliError $error) {
            throw match ($error->errorCode) {
                'timeout' => self::expired(),
                'interrupted' => CliError::interrupted('login cancelled, nothing was stored'),
                default => $error,
            };
        }

        /** @var array<string, mixed>&array{access_token: string} $grant */
        $grant = $state['grant'];

        return $grant;
    }

    private static function expired(): CliError
    {
        return CliError::timeout('the code expired before it was approved', 'Run unolia login again and approve the code in the browser.');
    }

    private function oauth(string $host): OAuthClient
    {
        return $this->runtime->clients()->oauth($host);
    }

    private function browserOverride(): ?string
    {
        $browser = $this->runtime->settings()->get('browser');

        return is_string($browser) && $browser !== '' ? $browser : null;
    }

    /** The path of an endpoint URL, relative to the host, since every call stays on that host. */
    private function path(string $url): string
    {
        if (! str_contains($url, '://')) {
            return ltrim($url, '/');
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? ltrim($path, '/') : '';
    }

    private function url(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        $list = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }
}
