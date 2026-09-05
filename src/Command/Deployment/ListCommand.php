<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Deployment;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Deployments\ListDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'deployment:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List deployments');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Filter by branch');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only deployments since, such as 7d');
        $this->addOption('all-websites', null, InputOption::VALUE_NONE, 'Every website of the project, not just the linked one');
    }

    public function examples(): array
    {
        return [
            'Recent deployments here' => 'unolia deployment list',
            'Failures on main' => 'unolia deployment list --status failed --branch main',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $context = $this->context(Need::None);
        $website = $this->optionBool('all-websites') ? null : ($this->optionString('website') !== null ? $this->websiteId() : $context->website);

        $rows = $this->rows(new ListDeployments($this->listQuery([
            'website' => $website,
            'project' => $website === null ? $context->project : null,
            'status' => $this->optionString('status'),
            'branch' => $this->optionString('branch'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'website' => 'Website',
                'status' => 'Status',
                'branch' => 'Branch',
                'commit' => 'Commit',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
            ],
            static fn (array $row): array => [
                'website' => Str::scalar(Arr::get($row, 'website.domain')),
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
