<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Project;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Projects\ListProjects;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'project:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the projects you can reach');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name or domain');
    }

    public function examples(): array
    {
        return [
            'Every project' => 'unolia project list',
            'Active ones' => 'unolia project list --status active',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListProjects($this->listQuery([
            'status' => $this->optionString('status'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table(), 'No projects yet.');

        return ExitCode::Ok;
    }

    /**
     * By name. The name opens the project page, the domain the site. The
     * counts say how much lives under the project; a zero is dim.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['status'] ?? null)),
            Column::make('name', 'Project')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['name'] ?? null))
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('domain', 'Domain')->cell(static function (array $row): Cell {
                $domain = $row['domain'] ?? null;

                return Cell::text(Str::scalar($domain, ''))->link(is_string($domain) && $domain !== '' ? 'https://'.$domain : null);
            }),
            Column::make('websites', 'Websites')->right()->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'counts.websites'), '0'))),
            Column::make('open_issues', 'Issues')->right()->cell(static function (array $row): Cell {
                $count = Arr::get($row, 'counts.open_issues');
                $cell = Cell::text(is_numeric($count) ? (string) $count : '');

                return is_numeric($count) && (int) $count > 0 ? $cell : $cell->dim();
            }),
            Column::make('updated_at', 'Updated')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['status'] ?? null)),
        )
            ->fields([
                'id' => 'Id',
                'name' => 'Name',
                'domain' => 'Domain',
                'status' => 'Status',
                'websites' => 'Websites',
                'open_issues' => 'Open issues',
                'updated_at' => 'Updated',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['name'] ?? null), Str::scalar($b['name'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 project' : $count.' projects');
    }
}
