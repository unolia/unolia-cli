<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Repo;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ListRepositories;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'repo:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the repositories of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every repository of the team');
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Filter by source');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by full name');
    }

    public function examples(): array
    {
        return [
            'Repositories here' => 'unolia repo list',
            'Everywhere' => 'unolia repo list --all-projects',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListRepositories($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'source' => $this->optionString('source'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table(), 'No repositories yet.');

        return ExitCode::Ok;
    }

    /**
     * By full name. The name opens the repository at its host, the source
     * wears the host's colour, the default branch and the last push are dim.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('full_name', 'Repository')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['full_name'] ?? null))
                ->link(is_string($row['full_url'] ?? null) ? $row['full_url'] : (is_string($row['url'] ?? null) ? $row['url'] : null))),
            Column::make('source', 'Source')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['source'] ?? null))->color(Tint::provider($row['source'] ?? null))),
            Column::make('default_branch', 'Branch')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['default_branch'] ?? null))->dim()),
            Column::make('last_pushed_at', 'Last push')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['last_pushed_at'] ?? null) ? $row['last_pushed_at'] : null))->dim()),
            Column::make('websites_count', 'Websites')->right()->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['websites_count'] ?? null, '0'))),
            Column::make('private')->cell(static fn (array $row): Cell => ($row['private'] ?? false) === true ? Cell::text('private')->dim() : Cell::empty()),
        )
            ->fields([
                'id' => 'Id',
                'full_name' => 'Repository',
                'source' => 'Source',
                'default_branch' => 'Default branch',
                'last_pushed_at' => 'Last push',
                'websites_count' => 'Websites',
                'private' => 'Private',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['full_name'] ?? null), Str::scalar($b['full_name'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 repository' : $count.' repositories');
    }
}
