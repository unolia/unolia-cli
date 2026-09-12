<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use React\Dns\Model\Message;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Domains\ShowDomain;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Dns;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * Ask a resolver. The one command that talks to something other than the
 * Unolia API. --all asks the public resolvers and the zone's own nameservers
 * and shows where they disagree, which is what "has it propagated" means.
 */
final class DigCommand extends BaseCommand
{
    use ResolvesZones;

    public const TYPES = [
        'A' => Message::TYPE_A,
        'AAAA' => Message::TYPE_AAAA,
        'CNAME' => Message::TYPE_CNAME,
        'NS' => Message::TYPE_NS,
        'MX' => Message::TYPE_MX,
        'PTR' => Message::TYPE_PTR,
        'SOA' => Message::TYPE_SOA,
        'SRV' => Message::TYPE_SRV,
        'SSHFP' => Message::TYPE_SSHFP,
        'TXT' => Message::TYPE_TXT,
        'CAA' => Message::TYPE_CAA,
    ];

    private const PUBLIC_RESOLVERS = ['1.1.1.1', '8.8.8.8', '9.9.9.9'];

    protected function canonical(): string
    {
        return 'dns:dig';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Ask a DNS resolver about a name');
    }

    protected function define(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name to look up, a full hostname or one relative to the zone');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type, A by default');
        $this->addOption('server', null, InputOption::VALUE_REQUIRED, 'Resolver to ask', '1.1.1.1');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Ask 1.1.1.1, 8.8.8.8, 9.9.9.9 and the zone\'s nameservers');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone a relative name belongs to');
    }

    public function examples(): array
    {
        return [
            'Look up an address' => 'unolia dig acme.com',
            'A relative name, in a checkout' => 'unolia dns dig www',
            'Every resolver at once' => 'unolia dig acme.com TXT --all',
            'Another resolver' => 'unolia dig acme.com --server 8.8.8.8',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $name = $this->argumentString('name') ?? $this->ask()->text('Name', '<name>', 'example.com');
        $typeName = strtoupper($this->argumentString('type')
            ?? ($this->ask()->interactive()
                ? $this->ask()->select('Record type', array_combine(array_keys(self::TYPES), array_keys(self::TYPES)), '<type>', 'A')
                : 'A'));

        if (! isset(self::TYPES[$typeName])) {
            throw CliError::usage(
                sprintf('%s is not a record type this command knows', $typeName),
                'Known types: '.implode(', ', array_keys(self::TYPES)),
                ['candidates' => array_keys(self::TYPES)],
            );
        }

        $zone = $this->zoneIfAny($name);
        $host = $zone === null ? self::tidy($name) : self::fullName($name, $zone);
        $servers = $this->optionBool('all') ? $this->allServers($zone) : [$this->optionString('server') ?? '1.1.1.1'];

        $rows = [];
        $answers = [];

        foreach ($servers as $server) {
            $found = $this->runtime()->get(Dns::class)->query($host, $typeName, self::TYPES[$typeName], $server);
            $values = array_map(static fn (array $row): string => $row['value'], $found);
            sort($values);
            $answers[$server] = $values;

            foreach ($found as $row) {
                $rows[] = ['server' => $server] + $row;
            }

            // With several resolvers a silent one is a row of its own; with one, the empty note says it.
            if ($found === [] && count($servers) > 1) {
                $rows[] = ['server' => $server, 'name' => $host, 'type' => $typeName, 'ttl' => null, 'value' => null];
            }
        }

        // With one resolver the table is what it answered. With several, a
        // row per resolver and answer, and a mark on the ones that disagree
        // with the majority.
        $agreed = self::majority($answers);
        $this->out()->table($rows, self::table(count($servers) > 1, $agreed), sprintf('No %s record for %s.', $typeName, $host));

        $anyAnswer = array_filter($answers, static fn (array $values): bool => $values !== []) !== [];

        return $anyAnswer ? ExitCode::Ok : ExitCode::NotFound;
    }

    /** A zone when one can be found without making noise: --zone, or the checkout when logged in. */
    private function zoneIfAny(string $name): ?string
    {
        $explicit = $this->optionString('zone');

        if ($explicit !== null) {
            return self::tidy($explicit);
        }

        if ($this->runtime()->hosts()->tokenFor($this->runtime()->host()) === null) {
            return null;
        }

        try {
            $zones = $this->projectZones();
        } catch (CliError|ApiException) {
            return null;
        }

        $tidy = self::tidy($name);

        foreach ($zones as $zone) {
            if ($tidy === $zone || str_ends_with($tidy, '.'.$zone)) {
                return $zone;
            }
        }

        // A bare label like "www" belongs to the only zone; a hostname with
        // dots that matches no zone is looked up as it is.
        return count($zones) === 1 && ! str_contains($tidy, '.') ? $zones[0] : null;
    }

    /**
     * @return list<string>
     */
    private function allServers(?string $zone): array
    {
        $servers = self::PUBLIC_RESOLVERS;

        if ($zone === null) {
            return $servers;
        }

        try {
            $expected = Arr::get($this->fetch(new ShowDomain($zone)), 'nameservers.expected');
        } catch (ApiException|CliError) {
            return $servers;
        }

        // Two of the zone's own nameservers are enough to know the provider's view.
        foreach (is_array($expected) ? array_slice($expected, 0, 2) : [] as $nameserver) {
            if (is_string($nameserver) && $nameserver !== '') {
                $servers[] = $nameserver;
            }
        }

        return $servers;
    }

    /**
     * The answer most resolvers give, as a sorted list of values.
     *
     * @param  array<string, list<string>>  $answers
     * @return list<string>
     */
    private static function majority(array $answers): array
    {
        $votes = [];

        foreach ($answers as $values) {
            $key = implode("\n", $values);
            $votes[$key] = ($votes[$key] ?? 0) + 1;
        }

        arsort($votes);
        $winner = array_key_first($votes);

        return $winner === null || $winner === '' ? [] : explode("\n", (string) $winner);
    }

    /**
     * @param  list<string>  $agreed
     */
    public static function table(bool $several, array $agreed): Table
    {
        return Table::make(
            Column::make('agree')->cell(static function (array $row) use ($several, $agreed): Cell {
                if (! $several) {
                    return Cell::empty();
                }

                if ($row['value'] === null) {
                    return $agreed === [] ? Cell::text('●')->color('green')->plain('') : Cell::text('○')->dim()->plain('missing');
                }

                return in_array($row['value'], $agreed, true) ? Cell::text('●')->color('green')->plain('') : Cell::text('✕')->color('red')->plain('differs');
            }),
            Column::make('server', $several ? 'Resolver' : '')->cell(static fn (array $row): Cell => $several ? Cell::text(Str::scalar($row['server'] ?? null, ''))->dim() : Cell::empty()),
            Column::make('name', 'Name')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['name'] ?? null, ''))),
            Column::make('type', 'Type')->cell(static function (array $row): Cell {
                $type = Str::scalar($row['type'] ?? null, '');
                $color = Tint::recordType($type);

                return $color === null ? Cell::text($type)->dim() : Cell::text($type)->color($color);
            }),
            Column::make('ttl', 'TTL')->right()->cell(static fn (array $row): Cell => Cell::text(is_numeric($row['ttl'] ?? null) ? RelativeTime::duration((int) $row['ttl']) : '')->dim()),
            Column::make('value', 'Value')->cell(static fn (array $row): Cell => $row['value'] === null ? Cell::text('no answer')->dim() : Cell::text(Str::scalar($row['value'], ''))),
        )
            ->fields(['server' => 'Resolver', 'name' => 'Name', 'type' => 'Type', 'ttl' => 'TTL', 'value' => 'Value']);
    }
}
