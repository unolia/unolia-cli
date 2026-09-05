<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Application;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Groups;
use Unolia\Cli\Console\HelpRenderer;

/**
 * `unolia help`, `unolia help website deploy`, and the target of every --help.
 */
final class HelpCommand extends BaseCommand
{
    private ?Command $command = null;

    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    protected function canonical(): string
    {
        return 'help';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Help for any command');
    }

    protected function define(): void
    {
        $this->addArgument('command_name', InputArgument::IS_ARRAY, 'The command to explain');
    }

    public function examples(): array
    {
        return [
            'Everything the CLI can do' => 'unolia help',
            'One command' => 'unolia help website deploy',
            'How to drive it from a script' => 'unolia help agents',
        ];
    }

    public function learnMore(): ?string
    {
        return null;
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $application = $this->getApplication();

        if (! $application instanceof Application) {
            return ExitCode::RemoteFailure;
        }

        $renderer = new HelpRenderer($this->runtime());

        if ($this->command !== null) {
            $this->out()->line($renderer->command($this->command));

            return ExitCode::Ok;
        }

        $words = $this->argumentList('command_name');

        if ($words === []) {
            $this->out()->line($renderer->root($application));

            return ExitCode::Ok;
        }

        if ($words === ['agents']) {
            $this->out()->line(HelpRenderer::agentNotes());

            return ExitCode::Ok;
        }

        $name = Groups::canonical(implode(' ', $words));

        if (! $application->has($name) && in_array($name, Groups::namespaces(), true)) {
            $this->out()->line($renderer->namespaceHelp($application, $name));

            return ExitCode::Ok;
        }

        if (! $application->has($name)) {
            throw CliError::usage(
                sprintf('there is no %s command', implode(' ', $words)),
                'Run unolia help to see the command list.',
            );
        }

        $this->out()->line($renderer->command($application->find($name)));

        return ExitCode::Ok;
    }
}
