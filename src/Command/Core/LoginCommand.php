<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Auth\Authenticator;
use Unolia\Cli\Auth\DeviceFlow;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Stdin;

/**
 * Authenticate: sign in through the browser with a one time code, or store a token you
 * already have. Scripts pipe a token in, people at a terminal get the browser.
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
        $this->addOption('token', null, InputOption::VALUE_REQUIRED, 'Store this token instead of signing in through the browser');
        $this->addOption('with-token', null, InputOption::VALUE_NONE, 'Read the token from stdin');
        $this->addOption('scopes', null, InputOption::VALUE_REQUIRED, 'Scopes to ask for, comma separated. The host picks sensible defaults');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the token in the dashboard, user@hostname by default');
        $this->addOption('no-browser', null, InputOption::VALUE_NONE, 'Print the URL instead of opening the browser');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to authenticate against');
    }

    public function examples(): array
    {
        return [
            'Sign in through the browser' => 'unolia login',
            'Ask for chosen scopes' => 'unolia login --scopes project:read,deployment:write',
            'From a script' => 'unolia login --with-token < token.txt',
            'Against a local host' => 'unolia login --host unolia.test',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->optionString('host') ?? $this->runtime()->host();
        $authenticator = $this->runtime()->get(Authenticator::class);
        $token = $this->tokenFromOptions();

        if ($token !== null) {
            return $this->report($host, $authenticator->loginWithToken($host, $token));
        }

        $existing = $this->runtime()->hosts()->tokenFor($host);

        if ($existing !== null && $this->alreadyValid($host, $existing)) {
            return ExitCode::Ok;
        }

        if (! $this->ask()->interactive()) {
            throw new CliError(
                'missing_input',
                'missing --token',
                ExitCode::Usage,
                'Pass --token <token>, pipe one with --with-token < token.txt, set UNOLIA_TOKEN, or run unolia login in a terminal to sign in through the browser.',
                ['flag' => '--token', 'candidates' => []],
            );
        }

        $scopes = $this->scopes();

        if ($scopes !== null) {
            DeviceFlow::rejectWildcard($host, $scopes);
        }

        $identity = $authenticator->loginWithDevice(
            $host,
            $scopes,
            $this->optionString('name'),
            ! $this->optionBool('no-browser'),
        );

        return $this->report($host, $identity);
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

    /**
     * @return list<string>|null
     */
    private function scopes(): ?array
    {
        $raw = $this->optionString('scopes');

        if ($raw === null) {
            return null;
        }

        $scopes = array_values(array_filter(array_map(trim(...), preg_split('/[\s,]+/', $raw) ?: []), static fn (string $scope): bool => $scope !== ''));

        if ($scopes === []) {
            throw CliError::usage('--scopes is empty', 'Pass a comma separated list, such as --scopes project:read,deployment:write');
        }

        return $scopes;
    }

    private function alreadyValid(string $host, string $token): bool
    {
        try {
            $identity = $this->runtime()->get(Authenticator::class)->identity($host, $token);
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

    /**
     * @param  array<string, mixed>  $identity
     */
    private function report(string $host, array $identity): ExitCode
    {
        if ($this->structured()) {
            $this->out()->record($identity);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Logged in to %s as %s (%s token)', $host, $identity['name'], $identity['kind']));

        $abilities = Authenticator::abilities($identity);

        if ($abilities !== null) {
            $this->out()->note($abilities);
        }

        return ExitCode::Ok;
    }
}
