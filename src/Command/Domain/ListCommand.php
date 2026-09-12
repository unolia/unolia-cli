<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\ListDomains;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * The DNS zones of a project, scoped to the checkout like every other list.
 */
final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the DNS zones of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every zone of the team, not just this project');
        $this->addOption('all-teams', null, InputOption::VALUE_NONE, 'Every zone your token can reach, across teams');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Filter by DNS provider');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name');
    }

    public function examples(): array
    {
        return [
            'Zones of this project' => 'unolia domains',
            'Every zone of the team' => 'unolia domains --all-projects',
            'Names only' => 'unolia domains --json domain',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $everywhere = $this->optionBool('all-projects') || $this->optionBool('all-teams');

        $rows = $this->rows(new ListDomains($this->listQuery([
            'project' => $everywhere ? null : $this->context(Need::None)->project,
            'all_teams' => $this->optionBool('all-teams') ? 1 : null,
            'provider' => $this->optionString('provider'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table($this->optionBool('all-teams')), 'No zones here yet.');

        return ExitCode::Ok;
    }

    /**
     * Alphabetical. The glyph is about the one thing that can be wrong with a
     * zone: whether the world's nameservers agree with the provider's. The
     * domain opens the live site, the project its page, the provider wears
     * its brand colour.
     */
    public static function table(bool $withTeam = false): Table
    {
        $nameservers = static fn (array $row): ?bool => is_bool(Arr::get($row, 'nameservers.ok')) ? Arr::get($row, 'nameservers.ok') : null;

        return Table::make(
            Column::make('status')->cell(static fn (array $row): Cell => match ($nameservers($row)) {
                true => Cell::text('●')->color('green')->plain(''),
                false => Cell::text('✕')->color('red')->plain(''),
                null => Cell::text('·')->dim()->plain(''),
            }),
            Column::make('domain', 'Domain')->cell(static function (array $row): Cell {
                $domain = $row['domain'] ?? null;

                return Cell::text(Str::scalar($domain))->link(is_string($domain) && $domain !== '' ? 'https://'.$domain : null);
            }),
            Column::make('provider', 'Provider')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'provider.label') ?? Arr::get($row, 'provider.slug'), ''))
                ->color(Tint::provider(Arr::get($row, 'provider.slug')))),
            Column::make('records_count', 'Records')->right()->cell(static fn (array $row): Cell => Cell::text(is_numeric($row['records_count'] ?? null) ? (string) $row['records_count'] : '')),
            Column::make('nameservers', 'Nameservers')->cell(static fn (array $row): Cell => match ($nameservers($row)) {
                true => Cell::text('at the provider')->dim()->plain('ok'),
                false => Cell::text('point elsewhere')->color('red'),
                null => Cell::text('not checked yet')->dim(),
            }),
            Column::make('project', 'Project')->cell(static function (array $row): Cell {
                $name = Arr::get($row, 'project.name');

                return is_string($name) ? Cell::text($name)->link(is_string($row['url'] ?? null) ? $row['url'] : null) : Cell::text('unassigned')->dim();
            }),
            Column::make('team', $withTeam ? 'Team' : '')->cell(static fn (array $row): Cell => $withTeam ? Cell::text(Str::scalar(Arr::get($row, 'team.name') ?? Arr::get($row, 'team.slug'), '')) : Cell::empty()),
            Column::make('synced_at', 'Synced')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['synced_at'] ?? null) ? $row['synced_at'] : null, null, 'never'))->dim()),
        )
            ->fields([
                'id' => 'Id',
                'domain' => 'Domain',
                'provider' => 'Provider',
                'records_count' => 'Records',
                'nameservers' => 'Nameservers',
                'project' => 'Project',
                'team' => 'Team',
                'synced_at' => 'Last synced',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['domain'] ?? null), Str::scalar($b['domain'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 zone' : $count.' zones');
    }
}
