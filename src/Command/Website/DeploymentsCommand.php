<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsiteDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Deployment\ListCommand as DeploymentListCommand;
use Unolia\Cli\Console\ExitCode;

final class DeploymentsCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'website:deployments';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the deployments of a website');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Filter by branch');
    }

    public function examples(): array
    {
        return [
            'Deployments of this website' => 'unolia website deployments',
            'Failures only' => 'unolia website deployments --status failed',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListWebsiteDeployments($this->websiteId(), $this->listQuery([
            'status' => $this->optionString('status'),
            'branch' => $this->optionString('branch'),
        ])));

        $this->out()->table($rows, DeploymentListCommand::table(), 'No deployments yet.');

        return ExitCode::Ok;
    }
}
