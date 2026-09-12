<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Incident;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Incidents\ListIncidents;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'incident:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List incidents');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status, open by default');
        $this->addOption('kind', null, InputOption::VALUE_REQUIRED, 'Filter by kind');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only incidents since, such as 7d');
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every project of the team');
    }

    public function examples(): array
    {
        return [
            'Open incidents' => 'unolia incident list',
            'Everything this week' => 'unolia incident list --status any --since 7d',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListIncidents($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'status' => $this->optionString('status'),
            'kind' => $this->optionString('kind'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->table($rows, self::table(), 'No incidents.');

        return ExitCode::Ok;
    }

    /**
     * As the API orders them. An open incident is red, a resolved one green,
     * and the word is always shown because both matter here. The severity
     * keeps its colour, the subject opens the site, the origin names the
     * deployment the incident started after.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))
                ->dim()
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['status'] ?? null)),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['status'] ?? null, '')),
            Column::make('severity', 'Severity')->cell(static fn (array $row): Cell => Status::severityWord($row['severity'] ?? null)),
            Column::make('kind', 'Kind')->cell(static fn (array $row): Cell => Cell::text(str_replace('_', ' ', Str::scalar($row['kind'] ?? null)))),
            Column::make('subject', 'Subject')->cell(static function (array $row): Cell {
                $subject = Arr::get($row, 'website.domain') ?? Arr::get($row, 'monitor.domain') ?? ($row['domain'] ?? null);

                return Cell::text(Str::scalar($subject))->link(is_string($subject) && $subject !== '' ? 'https://'.$subject : null);
            }),
            Column::make('started_at', 'Started')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null))->dim()),
            Column::make('duration_seconds', 'Lasted')->right()->cell(static fn (array $row): Cell => Cell::text(RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null))->dim()),
            Column::make('origin_deployment', 'Origin')->cell(static function (array $row): Cell {
                $id = Arr::get($row, 'origin_deployment.id');

                return Cell::text(is_scalar($id) ? 'deploy #'.$id : '')->dim();
            }),
        )
            ->fields([
                'id' => 'Id',
                'kind' => 'Kind',
                'severity' => 'Severity',
                'status' => 'Status',
                'subject' => 'Subject',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
                'origin_deployment' => 'Origin',
            ])
            ->footer(static fn (int $count): string => $count === 1 ? '1 incident' : $count.' incidents');
    }
}
