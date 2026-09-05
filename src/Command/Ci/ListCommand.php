<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ListRepositoryActions;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'ci:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the CI runs of this repository');
    }

    protected function define(): void
    {
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch, the current one by default');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Every branch');
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('workflow', null, InputOption::VALUE_REQUIRED, 'Filter by workflow name or path');
    }

    public function examples(): array
    {
        return [
            'Runs on this branch' => 'unolia ci',
            'Every branch' => 'unolia ci list --all-branches',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $branch = $this->optionBool('all-branches')
            ? null
            : ($this->optionString('branch') ?? $this->runtime()->context()->git()->branch());

        $rows = $this->rows(new ListRepositoryActions($this->repositoryId(), $this->listQuery([
            'branch' => $branch,
            'status' => $this->optionString('status'),
            'workflow' => $this->optionString('workflow'),
        ])));

        $this->out()->list(
            $rows,
            [
                'run_number' => 'Run',
                'name' => 'Workflow',
                'branch' => 'Branch',
                'event' => 'Event',
                'status' => 'Status',
                'conclusion' => 'Conclusion',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
            ],
            static fn (array $row): array => [
                'run_number' => '#'.Str::scalar($row['run_number'] ?? null, '?'),
                'started_at' => RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null),
                'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
            ],
            $branch === null ? 'No runs yet.' : sprintf('No runs on %s yet.', $branch),
        );

        return ExitCode::Ok;
    }
}
