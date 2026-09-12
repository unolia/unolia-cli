<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Support\Dns;
use Unolia\Cli\Support\Str;

/**
 * Unolia's records against what a resolver answers, one query per name and
 * type. The quick answer to "did it propagate everywhere".
 */
final class CheckCommand extends BaseCommand
{
    use ResolvesZones;

    /** Unolia's labels for what a resolver answers as TXT. */
    private const QUERY_TYPES = ['SPF' => 'TXT', 'DKIM' => 'TXT', 'DMARC' => 'TXT', 'BIMI' => 'TXT'];

    protected function canonical(): string
    {
        return 'dns:check';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Compare the records Unolia knows with what a resolver answers');
    }

    protected function define(): void
    {
        $this->addArgument('zone', InputArgument::OPTIONAL, 'The zone, the project\'s one by default');
        $this->addOption('server', null, InputOption::VALUE_REQUIRED, 'Resolver to ask', '1.1.1.1');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only these types, comma separated');
    }

    public function examples(): array
    {
        return [
            'This project\'s zone' => 'unolia dns check',
            'Against Google' => 'unolia dns check acme.com --server 8.8.8.8',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $zone = $this->zone($this->argumentString('zone'));
        $server = $this->optionString('server') ?? '1.1.1.1';
        $only = $this->optionString('type');
        $types = $only === null ? [] : array_map(strtoupper(...), array_filter(array_map(trim(...), explode(',', $only))));

        // Records grouped by name and the type a resolver answers with: SPF,
        // DKIM, DMARC and BIMI are Unolia's labels for TXT records. DNS
        // answers a set, so the comparison is set against set.
        $groups = [];

        foreach ($this->collection(new ListDomainRecords($zone, ['per_page' => 100])) as $record) {
            $label = strtoupper(Str::scalar($record['type'] ?? null, ''));
            $type = self::QUERY_TYPES[$label] ?? $label;

            if (! isset(DigCommand::TYPES[$type]) || ($types !== [] && ! in_array($label, $types, true) && ! in_array($type, $types, true))) {
                continue;
            }

            $name = self::tidy(Str::scalar($record['name'] ?? null, ''));
            $key = $name.'|'.$type;
            $groups[$key]['name'] = $name;
            $groups[$key]['type'] = $type;
            $groups[$key]['proxied'] = ($groups[$key]['proxied'] ?? false) || ($record['proxied'] ?? false) === true;
            $groups[$key]['unolia'][] = self::normalise(self::displayValue($record));
        }

        if ($groups === []) {
            $this->out()->note(sprintf('%s has no record this command can check.', $zone));

            return ExitCode::Ok;
        }

        $rows = [];
        $dns = $this->runtime()->get(Dns::class);

        foreach ($groups as $group) {
            $expected = $group['unolia'];
            sort($expected);

            // A proxied record answers the provider's edge, whatever the value
            // says here. The most a check can do is see that something answers.
            if ($group['proxied'] && in_array($group['type'], ['A', 'AAAA', 'CNAME'], true)) {
                try {
                    $answered = $dns->query($group['name'], $group['type'], DigCommand::TYPES[$group['type']], $server) !== []
                        || $dns->query($group['name'], 'A', DigCommand::TYPES['A'], $server) !== [];
                } catch (CliError) {
                    $answered = false;
                }

                $rows[] = ['name' => $group['name'], 'type' => $group['type'], 'result' => $answered ? 'proxied' : 'propagating', 'unolia' => $expected, 'resolver' => [], 'detail' => null];

                continue;
            }

            try {
                $answers = array_map(static fn (array $row): string => self::normalise($row['value']), $dns->query($group['name'], $group['type'], DigCommand::TYPES[$group['type']], $server));
            } catch (CliError $error) {
                $rows[] = ['name' => $group['name'], 'type' => $group['type'], 'result' => 'error', 'unolia' => $expected, 'resolver' => [], 'detail' => $error->getMessage()];

                continue;
            }

            sort($answers);

            $result = match (true) {
                $answers === $expected => 'match',
                $answers === [] => 'propagating',
                default => 'differs',
            };

            $rows[] = ['name' => $group['name'], 'type' => $group['type'], 'result' => $result, 'unolia' => $expected, 'resolver' => $answers, 'detail' => null];
        }

        if ($this->out()->face()->interactive && ! $this->structured()) {
            $this->out()->formatted("\n  <options=bold>".$zone.'</> <fg=gray>against '.$server.'</>');
        }

        $this->out()->table($rows, self::table($zone));

        $differ = count(array_filter($rows, static fn (array $row): bool => $row['result'] === 'differs' || $row['result'] === 'error'));

        return $differ === 0 ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    /** Resolvers and providers disagree on quotes, trailing dots and case in names; none of that is a difference. */
    /**
     * Only what differs, each side: what Unolia has and the resolver lacks,
     * and the other way round. Values both sides agree on are not the news.
     *
     * @param  list<string>  $unolia
     * @param  list<string>  $resolver
     */
    private static function difference(array $unolia, array $resolver): string
    {
        $onlyUnolia = array_values(array_diff($unolia, $resolver));
        $onlyResolver = array_values(array_diff($resolver, $unolia));
        $parts = [];

        if ($onlyUnolia !== []) {
            $parts[] = 'Unolia has '.Str::limit(implode(', ', $onlyUnolia), 44);
        }

        if ($onlyResolver !== []) {
            $parts[] = 'resolver has '.Str::limit(implode(', ', $onlyResolver), 44);
        }

        return implode(' · ', $parts);
    }

    private static function normalise(string $value): string
    {
        return strtolower(rtrim(trim(str_replace('"', '', $value)), '.'));
    }

    public static function table(string $zone): Table
    {
        return Table::make(
            Column::make('glyph')->cell(static fn (array $row): Cell => match ($row['result']) {
                'match', 'proxied' => Cell::text('●')->color('green')->plain(''),
                'propagating' => Cell::text('◐')->color('yellow')->plain(''),
                default => Cell::text('✕')->color('red')->plain(''),
            }),
            Column::make('name', 'Name')->cell(static function (array $row) use ($zone): Cell {
                $name = self::relativeName($row['name'], $zone);

                return $name === '@' ? Cell::text('@')->bold() : Cell::text($name);
            }),
            Column::make('type', 'Type')->cell(static function (array $row): Cell {
                $color = Tint::recordType($row['type']);

                return $color === null ? Cell::text($row['type'])->dim() : Cell::text($row['type'])->color($color);
            }),
            Column::make('result', 'Result')->cell(static fn (array $row): Cell => match ($row['result']) {
                'match' => Cell::text(implode(', ', array_map(static fn (string $value): string => Str::limit($value, 48), $row['unolia']))),
                'proxied' => Cell::text(Str::limit(implode(', ', $row['unolia']), 40).' · proxied, the resolver answers the provider\'s edge')->dim()->plain(implode(', ', $row['unolia']).' · proxied'),
                'propagating' => Cell::text('Unolia has '.Str::limit(implode(', ', $row['unolia']), 40).' · resolver has nothing yet')->color('yellow'),
                'error' => Cell::text(Str::scalar($row['detail'] ?? null, 'the resolver did not answer'))->color('red'),
                default => Cell::text(self::difference($row['unolia'], $row['resolver']))->color('red'),
            }),
        )
            ->fields(['name' => 'Name', 'type' => 'Type', 'result' => 'Result', 'unolia' => 'Unolia', 'resolver' => 'Resolver'])
            ->footer(static function (int $count): string {
                return $count === 1 ? '1 record checked' : $count.' records checked';
            });
    }
}
