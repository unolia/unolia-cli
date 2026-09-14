<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use RuntimeException;
use Saloon\Http\Response;
use Unolia\Cli\Config\Hosts;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * Every non 2xx answer and every connection failure. Turned into a CliError in one place
 * so exit codes stay consistent across commands.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param  array<mixed>  $body
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        string $message = '',
        public readonly ?string $host = null,
    ) {
        parent::__construct($message !== '' ? $message : sprintf('%s %s answered %d', $method, $path, $status));
    }

    public static function fromResponse(Response $response, string $method, string $path, ?string $host = null): self
    {
        /** @var mixed $decoded */
        $decoded = $response->json();

        return new self(
            status: $response->status(),
            body: is_array($decoded) ? $decoded : [],
            method: $method,
            path: $path,
            headers: $response->headers()->all(),
            host: $host,
        );
    }

    public static function network(string $host, string $reason): self
    {
        return new self(0, ['message' => $reason], 'GET', $host, [], sprintf('could not reach %s', $host));
    }

    public function toCliError(): CliError
    {
        return match (true) {
            $this->status === 0 => new CliError(
                'network_error',
                sprintf('could not reach %s', $this->path),
                ExitCode::RemoteFailure,
                'Check your connection, or UNOLIA_HOST if you point at another host.',
            ),
            $this->status === 400 && $this->errorCode() === 'team_required' => new CliError(
                'team_required',
                $this->message('this endpoint needs a team'),
                ExitCode::Usage,
                'Pass --team <slug>, or run unolia team switch <slug>.',
            ),
            $this->status === 401 => CliError::auth($this->message('your token was rejected')),
            $this->status === 402 => $this->upgradeRequired(),
            $this->status === 403 && $this->errorCode() === 'insufficient_scope' => CliError::forbidden(
                $this->message('your token does not have the scope this needs'),
                $this->details(),
                $this->scopeHint(),
            ),
            $this->status === 403 => CliError::forbidden(
                $this->message('you are not allowed to do that'),
                $this->details(),
                'Check the abilities of your token at https://app.unolia.com/user/api-tokens.',
            ),
            // The team header named a team this token cannot reach: a slug
            // from another host's config, most often. Not a missing record.
            $this->status === 404 && $this->errorCode() === 'team_not_found' => new CliError(
                'team_not_found',
                $this->message('no team of that name is reachable with this token'),
                ExitCode::Usage,
                'unolia teams lists the ones you can reach. Pass --team <slug>, or run unolia team switch <slug> (--local for this directory).',
                $this->details(),
            ),
            $this->status === 404 => CliError::notFound($this->message('not found')),
            // A 422 that says the resource cannot do this is not something a
            // different flag would fix, so it reads as a remote failure rather
            // than as a usage error.
            $this->status === 422 && $this->errorCode() === 'unsupported' => new CliError(
                'unsupported',
                $this->message('the API cannot do that here'),
                ExitCode::RemoteFailure,
            ),
            $this->status === 422 => CliError::usage($this->validationMessage(), null, $this->details()),
            $this->status === 409 && $this->errorCode() === 'run_rejected' => new CliError(
                'run_rejected',
                $this->message('the automation refused to start a run'),
                ExitCode::RemoteFailure,
                $this->runningHint(),
                $this->details(),
            ),
            $this->status === 409 && $this->errorCode() === 'provider_needs_attention' => new CliError(
                'provider_needs_attention',
                $this->message('the provider connection needs to be repaired first'),
                ExitCode::RemoteFailure,
                is_scalar(Arr::get($this->body, 'error.details.provider_id'))
                    ? sprintf('unolia provider fix %s opens the page where the connection is repaired.', Arr::get($this->body, 'error.details.provider_id'))
                    : null,
                $this->details(),
            ),
            $this->status === 409 => new CliError(
                $this->errorCode() ?? 'conflict',
                $this->message('the resource is not in a state where that can happen'),
                ExitCode::RemoteFailure,
                null,
                $this->details(),
            ),
            $this->status === 429 => new CliError(
                'rate_limited',
                'the API rate limit was reached',
                ExitCode::RemoteFailure,
                'Wait a moment and try again.',
                ['retry_after' => $this->header('Retry-After')],
            ),
            $this->status >= 500 => new CliError(
                'server_error',
                sprintf('the API answered %d for %s %s', $this->status, $this->method, $this->path),
                ExitCode::RemoteFailure,
                'Try again in a moment. If it lasts, check https://status.unolia.com.',
            ),
            // The API's own stable code when it sent one, so a caller reading
            // --json sees what the API called it rather than a flattened
            // "request_failed" for every status this does not name above.
            default => new CliError(
                $this->errorCode() ?? 'request_failed',
                $this->message(sprintf('the API answered %d', $this->status)),
                ExitCode::RemoteFailure,
            ),
        };
    }

    /** The stable code of the error envelope, when the API sent one. */
    public function errorCode(): ?string
    {
        $error = $this->body['error'] ?? null;

        if (is_array($error) && isset($error['code']) && is_string($error['code'])) {
            return $error['code'];
        }

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return null;
    }

    public function message(string $fallback): string
    {
        $error = $this->body['error'] ?? null;

        if (is_array($error) && isset($error['message']) && is_string($error['message']) && $error['message'] !== '') {
            return $error['message'];
        }

        $message = $this->body['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        $details = [];
        $error = $this->body['error'] ?? null;

        if (is_array($error)) {
            $details = $error;
            unset($details['message'], $details['code']);
        }

        $errors = $this->body['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $details['errors'] = $errors;
        }

        foreach (['feature', 'required_plan', 'upgrade_url', 'outcome'] as $key) {
            if (isset($this->body[$key])) {
                $details[$key] = $this->body[$key];
            }
        }

        return $details;
    }

    /**
     * The command that widens the token, with the scope the API named when it did.
     */
    /** The run already going, when the refusal names one, is what to follow instead. */
    private function runningHint(): ?string
    {
        $running = Arr::get($this->body, 'error.details.running');
        $first = is_array($running) ? ($running[0] ?? null) : null;

        return is_string($first) && $first !== ''
            ? sprintf('A run is already going: unolia automation watch %s follows it.', Str::shortId($first))
            : null;
    }

    private function scopeHint(): string
    {
        $scope = Arr::get($this->body, 'error.details.required_scope');
        $hint = 'Run unolia auth refresh --scopes '.(is_string($scope) && $scope !== '' ? $scope : '<scope>');

        if ($this->host !== null && $this->host !== Hosts::DEFAULT_HOST) {
            $hint .= ' --host '.$this->host;
        }

        return $hint;
    }

    private function upgradeRequired(): CliError
    {
        $details = $this->details();
        $plan = Arr::get($this->body, 'required_plan') ?? Arr::get($details, 'required_plan');
        $feature = Arr::get($this->body, 'feature') ?? Arr::get($details, 'feature');
        $url = Arr::get($this->body, 'upgrade_url') ?? Arr::get($details, 'upgrade_url');

        $message = $this->message('your plan does not include this');

        if (is_string($feature) && is_string($plan)) {
            $message = sprintf('%s needs the %s plan', $feature, $plan);
        }

        return new CliError(
            'upgrade_required',
            $message,
            ExitCode::Forbidden,
            is_string($url) && $url !== '' ? 'Upgrade at '.$url : null,
            $details,
        );
    }

    private function validationMessage(): string
    {
        $errors = $this->body['errors'] ?? null;
        $lines = [];

        if (is_array($errors)) {
            foreach ($errors as $field => $messages) {
                foreach ((array) $messages as $message) {
                    if (is_string($message)) {
                        $lines[] = sprintf('%s: %s', $field, $message);
                    }
                }
            }
        }

        if ($lines === []) {
            return $this->message('the request was refused');
        }

        return implode("\n", $lines);
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return null;
    }
}
