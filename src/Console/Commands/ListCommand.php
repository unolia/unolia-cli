<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Application;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\HelpRenderer;

/**
 * `unolia` with no arguments, and `unolia <namespace>`.
 */
final class ListCommand extends BaseCommand
{
    private ?string $namespace = null;

    public function forNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    protected function canonical(): string
    {
        return 'list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the commands');
        $this->setHidden();
    }

    protected function define(): void
    {
        $this->addArgument('namespace', InputArgument::OPTIONAL, 'Only list this namespace');
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
        $namespace = $this->namespace ?? $this->argumentString('namespace');

        $this->out()->line($namespace === null
            ? $renderer->root($application)
            : $renderer->namespaceHelp($application, $namespace));

        return ExitCode::Ok;
    }
}
