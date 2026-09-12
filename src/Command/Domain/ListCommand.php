<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Domains\ListDomains;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the domains this token can reach');
    }

    public function examples(): array
    {
        return [
            'Every domain' => 'unolia domain list',
            'Names only' => 'unolia domain list --json domain',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListDomains($this->listQuery()));
        $team = $this->optionString('team');

        if ($team !== null) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => in_array($team, [
                (string) (Arr::get($row, 'team.slug') ?? ''),
                (string) (Arr::get($row, 'team.id') ?? ''),
            ], true)));
        }

        $this->out()->table($rows, self::table(), 'No domains yet.');

        return ExitCode::Ok;
    }

    /** Alphabetical. The domain opens the site, the sync time is dim. */
    public static function table(): Table
    {
        return Table::make(
            Column::make('domain', 'Domain')->cell(static function (array $row): Cell {
                $domain = $row['domain'] ?? null;

                return Cell::text(Str::scalar($domain))->link(is_string($domain) && $domain !== '' ? 'https://'.$domain : null);
            }),
            Column::make('team', 'Team')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'team.name') ?? Arr::get($row, 'team.slug')))),
            Column::make('synced_at', 'Last synced')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['synced_at'] ?? null) ? $row['synced_at'] : null, null, 'never synced'))->dim()),
        )
            ->fields(['domain' => 'Domain', 'team' => 'Team', 'synced_at' => 'Last synced'])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['domain'] ?? null), Str::scalar($b['domain'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 domain' : $count.' domains');
    }
}
