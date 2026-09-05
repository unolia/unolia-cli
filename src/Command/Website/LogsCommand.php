<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsiteDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Concerns\StreamsLogs;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * The output of the latest deployment of a website.
 */
final class LogsCommand extends BaseCommand
{
    use ResolvesTargets;
    use StreamsLogs;

    protected function canonical(): string
    {
        return 'website:logs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print the output of the latest deployment');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
        $this->addOption('follow', 'f', InputOption::VALUE_NONE, 'Keep printing until the deployment ends');
        $this->addOption('raw', null, InputOption::VALUE_NONE, 'Keep the ANSI codes');
    }

    public function examples(): array
    {
        return [
            'The last deployment log' => 'unolia website logs',
            'Follow it' => 'unolia website logs --follow',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $deployments = $this->collection(new ListWebsiteDeployments($this->websiteId(), ['per_page' => 1]));
        $latest = $deployments[0] ?? null;

        if ($latest === null || ! is_numeric($latest['id'] ?? null)) {
            throw CliError::notFound('this website has no deployment yet', 'Run unolia deploy first.');
        }

        $this->printDeploymentOutput((int) $latest['id'], $this->optionBool('follow'), $this->optionBool('raw'));

        return ExitCode::Ok;
    }
}
