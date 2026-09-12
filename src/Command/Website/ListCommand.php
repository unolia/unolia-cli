<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsites;
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
        return 'website:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the websites of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every website of the team, not just this project');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Filter by provider short name');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by domain or name');
    }

    public function examples(): array
    {
        return [
            'Websites of this project' => 'unolia website list',
            'Every website of the team' => 'unolia website list --all-projects',
            'Domains only' => 'unolia website list --json id,domain',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $project = $this->optionBool('all-projects') ? null : $this->context(Need::None)->project;

        $rows = $this->rows(new ListWebsites($this->listQuery([
            'project' => $project,
            'provider' => $this->optionString('provider'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table(), 'No websites here yet.');

        return ExitCode::Ok;
    }

    /**
     * Ordered by project then domain. The id is dim and right aligned, the
     * state is a glyph with a word only when it is not "active", the domain
     * opens the live site and the project its Unolia page. The data faces keep
     * the last deploy as well, which the table leaves out.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['status'] ?? null)),
            Column::make('domain', 'Website')->cell(static function (array $row): Cell {
                $domain = $row['domain'] ?? null;

                return Cell::text(Str::scalar($domain))->link(is_string($domain) && $domain !== '' ? 'https://'.$domain : null);
            }),
            Column::make('project', 'Project')->cell(static function (array $row): Cell {
                $url = Str::scalar($row['url'] ?? null);

                // The website page is <project page>/websites/<id>; two steps up is the project.
                return Cell::text(Str::scalar(Arr::get($row, 'project.name')))
                    ->link(preg_match('#^(.*)/websites/\\d+$#', $url, $parts) === 1 ? $parts[1] : null);
            }),
            Column::make('provider', 'Provider')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'provider.label') ?? Arr::get($row, 'provider.slug')))
                ->color(Tint::provider(Arr::get($row, 'provider.slug')))),
            Column::make('php_version', 'PHP')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['php_version'] ?? null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['status'] ?? null)),
        )
            ->fields([
                'id' => 'Id',
                'domain' => 'Domain',
                'provider' => 'Provider',
                'project' => 'Project',
                'php_version' => 'PHP',
                'last_deployed_at' => 'Last deploy',
                'status' => 'Status',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar(Arr::get($a, 'project.name')), Str::scalar(Arr::get($b, 'project.name')))
                ?: strcasecmp(Str::scalar($a['domain'] ?? null), Str::scalar($b['domain'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 website' : $count.' websites');
    }
}
