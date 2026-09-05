<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Core\CurrentAuthenticated;
use Unolia\Cli\Api\Requests\Core\CurrentToken;
use Unolia\Cli\Api\Requests\Core\Login;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Browser;
use Unolia\Cli\Support\Stdin;

/**
 * Authenticate: paste a token, read one from stdin, or sign in with email and password.
 */
final class LoginCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'login';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Authenticate with Unolia');
    }

    protected function define(): void
    {
        $this->addOption('token', null, InputOption::VALUE_REQUIRED, 'Authenticate with this token');
        $this->addOption('with-token', null, InputOption::VALUE_NONE, 'Read the token from stdin');
        $this->addOption('web', null, InputOption::VALUE_NONE, 'Open the token page in the browser first');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to authenticate against');
    }

    public function examples(): array
    {
        return [
            'Paste a token' => 'unolia login',
            'From a script' => 'unolia login --with-token < token.txt',
            'Against a local host' => 'unolia login --host unolia.test --token utk_...',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->optionString('host') ?? $this->runtime()->host();
        $token = $this->tokenFromOptions();

        if ($token !== null) {
            return $this->store($host, $token);
        }

        $existing = $this->runtime()->hosts()->tokenFor($host);

        if ($existing !== null && $this->alreadyValid($host, $existing)) {
            return ExitCode::Ok;
        }

        if (! $this->ask()->interactive()) {
            throw CliError::missingInput('--token');
        }

        if ($this->optionBool('web')) {
            $this->openTokenPage($host);
        }

        $method = $this->optionBool('web') ? 'paste' : $this->ask()->select(
            'How do you want to authenticate?',
            ['paste' => 'Paste a token (recommended)', 'password' => 'Email and password'],
            '--token',
            'paste',
        );

        $token = $method === 'paste'
            ? $this->ask()->password('Token', '--token', 'Create one at https://'.$host.'/user/api-tokens/create')
            : $this->signIn($host);

        return $this->store($host, $token);
    }

    private function tokenFromOptions(): ?string
    {
        $token = $this->optionString('token');

        if ($token !== null) {
            return $token;
        }

        if (! $this->optionBool('with-token')) {
            return null;
        }

        $piped = trim($this->runtime()->get(Stdin::class)->read());

        if ($piped === '') {
            throw CliError::usage('nothing was piped into --with-token', 'Try: unolia login --with-token < token.txt');
        }

        return $piped;
    }

    private function alreadyValid(string $host, string $token): bool
    {
        try {
            $identity = $this->identity($host, $token);
        } catch (CliError $error) {
            // identity() turns a 401 into this. Anything else, a network
            // failure or a 5xx, is not a reason to replace a token that may
            // well be fine, so it surfaces as the error it is.
            if ($error->errorCode !== 'unauthenticated') {
                throw $error;
            }

            $this->out()->warn('Your stored token is no longer valid, so this is a fresh login.');

            return false;
        }

        $this->out()->info(sprintf(
            'Already logged in as %s (%s token%s). Run unolia logout first to switch.',
            $identity['name'],
            $identity['kind'],
            $identity['expires_at'] !== null ? ', expires '.substr((string) $identity['expires_at'], 0, 10) : '',
        ));

        if ($this->structured()) {
            $this->out()->record($identity);
        }

        return true;
    }

    private function signIn(string $host): string
    {
        $email = $this->ask()->text('Email', '--token', 'you@example.com', '', 'Signing in to '.$host);
        $password = $this->ask()->password('Password', '--token');
        $tokenName = sprintf('unolia-cli %s@%s', get_current_user() ?: 'cli', gethostname() ?: 'localhost');

        $auth = $this->runtime()->clients()->auth($host);

        try {
            $response = $auth->send(new Login($email, $password, $tokenName));
        } catch (ApiException $exception) {
            if ($exception->status !== 422 || ! $this->needsTwoFactor($exception)) {
                throw $exception;
            }

            $code = $this->ask()->text('Two factor code', '--token', '123456');
            $response = $auth->send(new Login($email, $password, $tokenName, $code));
        }

        $token = $response->json('data.token');

        if (! is_string($token) || $token === '') {
            throw CliError::auth('the API did not return a token');
        }

        $this->out()->warn('This token carries every ability and expires in a year.');
        $this->out()->warn(sprintf('A scoped token can be created at https://%s/user/api-tokens/create', $host));

        return $token;
    }

    private function needsTwoFactor(ApiException $exception): bool
    {
        $errors = $exception->body['errors'] ?? null;

        return is_array($errors) && isset($errors['two_factor_code']);
    }

    private function store(string $host, string $token): ExitCode
    {
        $identity = $this->identity($host, $token);

        $this->runtime()->hosts()->put($host, $token, (string) $identity['kind'], (string) $identity['name']);

        if ($this->structured()) {
            $this->out()->record($identity);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Logged in to %s as %s (%s token)', $host, $identity['name'], $identity['kind']));

        $scopes = $identity['scopes'];

        if (is_array($scopes) && $scopes !== ['*'] && $scopes !== []) {
            $this->out()->note('Abilities: '.implode(', ', array_map(strval(...), $scopes)));
        }

        return ExitCode::Ok;
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(string $host, string $token): array
    {
        $client = $this->runtime()->clients()->make($host, $token);

        try {
            $tokenData = $client->send(new CurrentToken)->json('data');
            $principal = $client->send(new CurrentAuthenticated)->json('data');
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                throw CliError::auth('that token was rejected by '.$host, 'Create a new one at https://'.$host.'/user/api-tokens/create');
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
            'scopes' => $tokenData['scopes'] ?? [],
            'expires_at' => $tokenData['expires_at'] ?? null,
        ];
    }

    private function openTokenPage(string $host): void
    {
        $url = sprintf('https://%s/user/api-tokens/create', $host);
        $opened = $this->runtime()->get(Browser::class)->open($url, $this->browserOverride());

        $this->out()->note($opened ? 'Opened '.$url : 'Open '.$url.' to create a token.');
    }

    private function browserOverride(): ?string
    {
        $browser = $this->runtime()->settings()->get('browser');

        return is_string($browser) && $browser !== '' ? $browser : null;
    }
}
