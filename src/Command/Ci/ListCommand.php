<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ListRepositoryActions;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    use ResolvesTargets;

    /** Workflow names are cut here: a dependency bot names a run after every package it bumps. */
    private const NAME_WIDTH = 48;

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

        $this->out()->table($rows, self::table(), $branch === null ? 'No runs yet.' : sprintf('No runs on %s yet.', $branch));

        return ExitCode::Ok;
    }

    /**
     * Newest first. A run that ended is told by its conclusion, one still
     * going by its status, so one glyph and one word cover both. The run
     * number opens the run on Unolia.
     */
    public static function table(): Table
    {
        $outcome = static fn (array $row): mixed => $row['conclusion'] ?? $row['status'] ?? null;

        return Table::make(
            Column::make('run_number', 'Run')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['run_number'] ?? null, '?'))
                ->dim()
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('outcome')->cell(static fn (array $row): Cell => Status::glyph($outcome($row))),
            Column::make('name', 'Workflow')->cell(static fn (array $row): Cell => Cell::text(Str::limit(is_string($row['name'] ?? null) ? $row['name'] : '', self::NAME_WIDTH))),
            Column::make('branch', 'Branch')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['branch'] ?? null))),
            Column::make('event', 'Event')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['event'] ?? null))->dim()),
            Column::make('head_sha', 'Commit')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(is_string($row['head_sha'] ?? null) ? substr($row['head_sha'], 0, 7) : null))->dim()),
            Column::make('started_at', 'Started')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null))->dim()),
            Column::make('duration_seconds', 'Took')->right()->cell(static fn (array $row): Cell => Cell::text(RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($outcome($row), 'success')),
        )
            ->fields([
                'run_number' => 'Run',
                'name' => 'Workflow',
                'branch' => 'Branch',
                'event' => 'Event',
                'status' => 'Status',
                'conclusion' => 'Conclusion',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
            ])
            ->footer(static fn (int $count): string => $count === 1 ? '1 run' : $count.' runs');
    }
}
