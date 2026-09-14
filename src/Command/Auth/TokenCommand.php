<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Auth;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * The token in use, for a script that has to call the API itself. This is the one
 * place a token is ever printed, and only because you asked for it by name.
 */
final class TokenCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'auth:token';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print the token in use, for scripts');
    }

    protected function define(): void
    {
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host whose token to print');
    }

    public function examples(): array
    {
        return [
            'Use it with curl' => 'curl -H "Authorization: Bearer $(unolia auth token)" https://app.unolia.com/api/v2/teams',
            'As JSON' => 'unolia auth token --json',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->optionString('host') ?? $this->runtime()->host();
        $token = $this->runtime()->hosts()->tokenFor($host);

        if ($token === null) {
            throw CliError::auth('you are not logged in to '.$host);
        }

        if ($this->structured()) {
            $this->out()->record(['token' => $token]);

            return ExitCode::Ok;
        }

        $this->out()->raw($token."\n");

        return ExitCode::Ok;
    }
}
