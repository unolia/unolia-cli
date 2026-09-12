<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Api\Requests\Domains\ShowDomain;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * The records of a zone. Bare `unolia dns` in a checkout lists the project's
 * zone, the way `unolia ci` lists the repository's runs.
 */
final class ListCommand extends BaseCommand
{
    use ResolvesZones;

    /** Values are cut here so the row stays on one line; a pipe gets them whole. */
    private const VALUE_WIDTH = 48;

    protected function canonical(): string
    {
        return 'dns:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the DNS records of a zone');
    }

    protected function define(): void
    {
        $this->addArgument('zone', InputArgument::OPTIONAL, 'The zone, the project\'s one by default');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only these types, comma separated');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Only this name, relative or full');
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Only records in this state');
    }

    public function examples(): array
    {
        return [
            'Records of this project\'s zone' => 'unolia dns',
            'Another zone' => 'unolia dns acme.com',
            'Mail records only' => 'unolia dns --type MX,TXT',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $zone = $this->zone($this->argumentString('zone'));
        $name = $this->optionString('name');
        $type = $this->optionString('type');

        $rows = $this->rows(new ListDomainRecords($zone, $this->listQuery([
            'type' => $type === null ? null : strtoupper($type),
            'name' => $name === null ? null : self::fullName($name, $zone),
            'state' => $this->optionString('state'),
        ])));

        // Older servers ignore the filters; keep the rows that match anyway.
        $types = $type === null ? [] : array_map(strtoupper(...), array_filter(array_map(trim(...), explode(',', $type))));
        $rows = array_values(array_filter($rows, function (array $row) use ($types, $name, $zone): bool {
            if ($types !== [] && ! in_array(strtoupper(Str::scalar($row['type'] ?? null, '')), $types, true)) {
                return false;
            }

            return $name === null || self::tidy(Str::scalar($row['name'] ?? null, '')) === self::fullName($name, $zone);
        }));

        if ($this->out()->face()->format === Format::Table && $this->out()->face()->interactive) {
            $this->out()->formatted($this->heading($zone));
        }

        $this->out()->table($rows, self::table($zone), sprintf('%s has no record%s.', $zone, $types === [] && $name === null ? '' : ' like that'));

        return ExitCode::Ok;
    }

    /** One dim line above the table: the zone, who hosts it, whether its nameservers agree, when it was synced. */
    private function heading(string $zone): string
    {
        try {
            $domain = $this->fetch(new ShowDomain($zone));
        } catch (ApiException) {
            return '';
        }

        $ok = Arr::get($domain, 'nameservers.ok');
        $parts = array_filter([
            Str::scalar(Arr::get($domain, 'provider.label'), ''),
            match ($ok) {
                true => 'nameservers ok',
                false => 'nameservers point elsewhere',
                default => '',
            },
            is_string($domain['synced_at'] ?? null) ? 'synced '.RelativeTime::ago($domain['synced_at']) : '',
        ], static fn (string $part): bool => $part !== '');

        return "\n  <options=bold>".$zone.'</>'.($parts === [] ? '' : ' <fg=gray>· '.implode(' · ', $parts).'</>');
    }

    /**
     * Apex first, then by name and type. Names are relative to the zone, types
     * wear their family colour, TTLs read like people say them and are dim,
     * the state is a glyph with a word only when it is not verified.
     */
    public static function table(string $zone): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['state'] ?? null)),
            Column::make('name', 'Name')->cell(static function (array $row) use ($zone): Cell {
                $name = self::relativeName($row['name'] ?? null, $zone);

                return $name === '@' ? Cell::text('@')->bold() : Cell::text($name);
            }),
            Column::make('type', 'Type')->cell(static function (array $row): Cell {
                $type = Str::scalar($row['type'] ?? null, '');
                $color = Tint::recordType($type);

                return $color === null ? Cell::text($type)->dim() : Cell::text($type)->color($color);
            }),
            Column::make('ttl', 'TTL')->right()->cell(static fn (array $row): Cell => Cell::text(is_numeric($row['ttl'] ?? null) ? RelativeTime::duration((int) $row['ttl']) : '')->dim()),
            Column::make('value', 'Value')->cell(static function (array $row): Cell {
                $value = Str::scalar($row['value'] ?? null, '');
                $priority = $row['priority'] ?? null;

                return Cell::text(Str::limit((is_numeric($priority) ? $priority.' ' : '').rtrim($value, '.'), self::VALUE_WIDTH))->plain((is_numeric($priority) ? $priority.' ' : '').$value);
            }),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['state'] ?? null, 'verified')),
        )
            ->fields([
                'id' => 'Id',
                'name' => 'Name',
                'type' => 'Type',
                'ttl' => 'TTL',
                'priority' => 'Priority',
                'value' => 'Value',
                'state' => 'State',
            ])
            ->sort(static function (array $a, array $b) use ($zone): int {
                $left = self::relativeName($a['name'] ?? null, $zone);
                $right = self::relativeName($b['name'] ?? null, $zone);

                return ($left === '@' ? 0 : 1) <=> ($right === '@' ? 0 : 1)
                    ?: strcasecmp($left, $right)
                    ?: strcasecmp(Str::scalar($a['type'] ?? null, ''), Str::scalar($b['type'] ?? null, ''))
                    ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
            })
            ->footer(static fn (int $count): string => $count === 1 ? '1 record' : $count.' records');
    }
}
