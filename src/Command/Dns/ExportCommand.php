<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;

/**
 * The zone as a zone file, ready to paste into another provider or to keep
 * in git. The data faces get the rows instead.
 */
final class ExportCommand extends BaseCommand
{
    use ResolvesZones;

    protected function canonical(): string
    {
        return 'dns:export';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print a zone as a zone file');
    }

    protected function define(): void
    {
        $this->addArgument('zone', InputArgument::OPTIONAL, 'The zone, the project\'s one by default');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only these types, comma separated');
    }

    public function examples(): array
    {
        return [
            'To a file' => 'unolia dns export acme.com > acme.com.zone',
            'As rows' => 'unolia dns export acme.com --json',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $zone = $this->zone($this->argumentString('zone'));
        $only = $this->optionString('type');
        $types = $only === null ? [] : array_map(strtoupper(...), array_filter(array_map(trim(...), explode(',', $only))));

        $rows = array_values(array_filter(
            $this->collection(new ListDomainRecords($zone, ['per_page' => 100])),
            static fn (array $row): bool => $types === [] || in_array(strtoupper(Str::scalar($row['type'] ?? null, '')), $types, true),
        ));

        $rows = ListCommand::table($zone)->order($rows);

        if ($this->structured()) {
            $this->out()->list($rows, ['id' => 'Id', 'name' => 'Name', 'type' => 'Type', 'ttl' => 'TTL', 'priority' => 'Priority', 'value' => 'Value', 'state' => 'State']);

            return ExitCode::Ok;
        }

        $lines = ['$ORIGIN '.$zone.'.'];
        $width = 0;

        foreach ($rows as $row) {
            $width = max($width, strlen(self::relativeName($row['name'] ?? null, $zone)));
        }

        foreach ($rows as $row) {
            $type = strtoupper(Str::scalar($row['type'] ?? null, ''));
            $value = self::displayValue($row);

            if ($type === 'TXT' && ! str_starts_with($value, '"')) {
                $value = '"'.str_replace('"', '\"', $value).'"';
            }

            if (in_array($type, ['CNAME', 'NS', 'MX', 'PTR'], true) && ! str_ends_with($value, '.') && str_contains($value, '.')) {
                $value .= '.';
            }

            $lines[] = rtrim(sprintf(
                '%s  %s IN %s %s',
                str_pad(self::relativeName($row['name'] ?? null, $zone), $width),
                str_pad(is_numeric($row['ttl'] ?? null) ? (string) $row['ttl'] : '', 6),
                str_pad($type, 5),
                $value,
            ));
        }

        $this->out()->line(implode("\n", $lines));

        return ExitCode::Ok;
    }
}
