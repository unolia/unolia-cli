<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\Website\DeployCommand as WebsiteDeployCommand;
use Unolia\Cli\Console\CliError;

/**
 * `unolia deploy` and `unolia deploy staging`, the short way to reach website deploy.
 */
final class DeployCommand extends WebsiteDeployCommand
{
    protected function canonical(): string
    {
        return 'deploy';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Deploy the website linked to this directory');
    }

    protected function define(): void
    {
        $this->addArgument('environment', InputArgument::OPTIONAL, 'An environment name from .unolia/config.json');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Block until the deployment finishes, also in a pipe');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Return as soon as the deployment is queued instead of following it');
        $this->addWatchOptions();
    }

    public function examples(): array
    {
        return [
            'Deploy this directory and watch it' => 'unolia deploy',
            'Deploy an environment' => 'unolia deploy staging',
            'Queue it and come back later' => 'unolia deploy --no-progress',
        ];
    }

    protected function targetWebsiteId(): int
    {
        $environment = $this->argumentString('environment');

        if ($environment === null) {
            return $this->websiteId();
        }

        $environments = $this->runtime()->context()->environments();

        if (! isset($environments[$environment])) {
            throw CliError::usage(
                sprintf('there is no %s environment here', $environment),
                $environments === []
                    ? 'Add an environments map to .unolia/config.json, or run unolia init.'
                    : 'Known environments: '.implode(', ', array_keys($environments)),
                ['candidates' => array_keys($environments)],
            );
        }

        return $environments[$environment];
    }
}
