<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Deployment;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\StreamsLogs;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

final class LogsCommand extends BaseCommand
{
    use StreamsLogs;

    protected function canonical(): string
    {
        return 'deployment:logs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print the output of a deployment');
    }

    protected function define(): void
    {
        $this->addArgument('deployment', InputArgument::REQUIRED, 'Deployment id');
        $this->addOption('follow', 'f', InputOption::VALUE_NONE, 'Keep printing until the deployment ends');
        $this->addOption('raw', null, InputOption::VALUE_NONE, 'Keep the ANSI codes');
    }

    public function examples(): array
    {
        return [
            'The whole log' => 'unolia deployment logs 4812',
            'Follow it' => 'unolia deployment logs 4812 --follow',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('deployment');

        if (! ctype_digit($id)) {
            throw CliError::usage('the deployment id must be a number');
        }

        $this->printDeploymentOutput((int) $id, $this->optionBool('follow'), $this->optionBool('raw'));

        return ExitCode::Ok;
    }
}
