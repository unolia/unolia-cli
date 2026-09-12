<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class RunsCommand extends BaseCommand
{
    use ResolvesRuns;

    /** Summaries are cut here so the row stays on one line. */
    private const SUMMARY_WIDTH = 48;

    protected function canonical(): string
    {
        return 'automation:runs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List automation runs');
    }

    protected function define(): void
    {
        $this->addOption('automation', null, InputOption::VALUE_REQUIRED, 'Only runs of this automation');
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Filter by state');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only runs since, such as 7d');
    }

    public function examples(): array
    {
        return [
            'Recent runs' => 'unolia automation runs',
            'What is stuck' => 'unolia automation runs --state awaiting_input',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $automation = $this->optionString('automation');

        $rows = $this->rows(new ListAutomationRuns($this->listQuery([
            'automation' => $automation === null ? null : $this->automationId($automation),
            'state' => $this->optionString('state'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->table($rows, self::table(), 'No runs yet.');

        return ExitCode::Ok;
    }

    /**
     * Newest first, as the API hands them. The run is named by its short id,
     * the last characters of the ulid, which is what every run command takes.
     * The state is a glyph with a word unless the run completed.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('ulid', 'Run')->cell(static fn (array $row): Cell => Cell::text(Str::shortId($row['ulid'] ?? null))
                ->dim()
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['state'] ?? null)),
            Column::make('automation', 'Automation')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'automation.name')))),
            Column::make('trigger', 'Trigger')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'trigger.kind')))->dim()),
            Column::make('summary', 'Summary')->cell(static fn (array $row): Cell => Cell::text(Str::limit(is_string($row['summary'] ?? null) ? $row['summary'] : '', self::SUMMARY_WIDTH))),
            Column::make('started_at', 'Started')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null))->dim()),
            Column::make('duration_seconds', 'Took')->right()->cell(static fn (array $row): Cell => Cell::text(RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['state'] ?? null, 'completed')),
        )
            ->fields([
                'ulid' => 'Run',
                'automation' => 'Automation',
                'trigger' => 'Trigger',
                'state' => 'State',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
                'summary' => 'Summary',
            ])
            ->footer(static fn (int $count): string => $count === 1 ? '1 run' : $count.' runs');
    }
}
