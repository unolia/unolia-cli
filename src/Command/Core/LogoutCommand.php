<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Core\Logout;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * Revoke the token on the server when we can, and always drop it locally.
 */
final class LogoutCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'logout';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Remove the stored token for this host');
    }

    protected function define(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Drop the local token even when the API call fails');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to log out of');
    }

    public function examples(): array
    {
        return [
            'Log out' => 'unolia logout',
            'Drop a revoked token' => 'unolia logout --force',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->optionString('host') ?? $this->runtime()->host();
        $source = $this->runtime()->hosts()->tokenSource($host);

        // A token handed over by the environment belongs to whoever set it, a
        // CI secret more often than not. Revoking it from a shell would take a
        // pipeline down, so it is left alone, the way gh leaves GH_TOKEN alone.
        if ($source !== null && $source !== 'hosts.json') {
            throw CliError::usage(
                sprintf('the token comes from %s, so there is nothing stored here to log out of', $source),
                'Unset the variable, or revoke the token from the dashboard.',
            );
        }

        $token = $this->runtime()->hosts()->tokenFor($host);

        if ($token === null) {
            throw CliError::auth('you are not logged in to '.$host, 'Run unolia login');
        }

        $revoked = true;

        try {
            $this->runtime()->clients()->auth($host, $token)->send(new Logout);
        } catch (ApiException $exception) {
            $revoked = false;

            if ($exception->status !== 401 && ! $this->optionBool('force')) {
                throw $exception;
            }
        }

        $this->runtime()->hosts()->forget($host);

        if ($this->structured()) {
            $this->out()->record(['host' => $host, 'revoked' => $revoked]);

            return ExitCode::Ok;
        }

        $this->out()->info('Logged out of '.$host);

        if (! $revoked) {
            $this->out()->note('The token was already gone on the server, so only the local copy was removed.');
        }

        return ExitCode::Ok;
    }
}
