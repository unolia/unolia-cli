<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsiteDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

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

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'status' => 'Status',
                'branch' => 'Branch',
                'commit' => 'Commit',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
            ],
            static fn (array $row): array => [
                'branch' => Str::scalar(Arr::get($row, 'commit.branch')),
                'commit' => Str::scalar(Arr::get($row, 'commit.short')),
                'started_at' => RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null),
                'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
            ],
            'No deployments yet.',
        );

        return ExitCode::Ok;
    }
}
