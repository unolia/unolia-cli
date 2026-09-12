<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Server;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Servers\ListServers;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'server:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the managed servers of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every server of the team');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Filter by provider');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name');
    }

    public function examples(): array
    {
        return [
            'Servers here' => 'unolia server list',
            'PHP versions' => 'unolia server list --json name,php_version',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListServers($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'provider' => $this->optionString('provider'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table(), 'No managed servers here.');

        return ExitCode::Ok;
    }

    /**
     * By name. The name opens the server page, the provider wears its brand
     * colour, the versions are dim because they only matter when they differ.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['status'] ?? null)),
            Column::make('name', 'Server')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['name'] ?? null))
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('provider', 'Provider')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'provider.slug')))
                ->color(Tint::provider(Arr::get($row, 'provider.slug')))),
            Column::make('type', 'Type')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['type'] ?? null))->dim()),
            Column::make('public_ipv4', 'IP')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['public_ipv4'] ?? null))),
            Column::make('php_version', 'PHP')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['php_version'] ?? null))->dim()),
            Column::make('database', 'Database')->cell(static fn (array $row): Cell => Cell::text(self::database($row))->dim()),
            Column::make('ubuntu_version', 'Ubuntu')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['ubuntu_version'] ?? null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['status'] ?? null, 'connected')),
        )
            ->fields([
                'id' => 'Id',
                'name' => 'Name',
                'provider' => 'Provider',
                'type' => 'Type',
                'public_ipv4' => 'IP',
                'php_version' => 'PHP',
                'database' => 'Database',
                'ubuntu_version' => 'Ubuntu',
                'status' => 'Status',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['name'] ?? null), Str::scalar($b['name'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 server' : $count.' servers');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function database(array $row): string
    {
        $engine = Arr::get($row, 'database.engine');
        $version = Arr::get($row, 'database.version');

        if (! is_string($engine)) {
            return Str::scalar(Arr::get($row, 'database.raw'), '');
        }

        return is_scalar($version) ? $engine.' '.$version : $engine;
    }
}
