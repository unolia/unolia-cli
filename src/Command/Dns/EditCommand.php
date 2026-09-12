<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\UpdateRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\PropagatesRecords;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * Change the name, value or TTL of one record, named by id or by name and type.
 */
final class EditCommand extends BaseCommand
{
    use PropagatesRecords;
    use ResolvesZones;

    protected function canonical(): string
    {
        return 'dns:edit';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Change the name, value or TTL of a DNS record');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id, or its name');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type, when the name alone is not enough');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone, the project\'s one by default');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'The new name, relative to the zone');
        $this->addOption('value', null, InputOption::VALUE_REQUIRED, 'The new value');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'The new time to live in seconds');
        $this->addOption('priority', null, InputOption::VALUE_REQUIRED, 'The new priority, for MX and SRV');
        $this->addPropagationOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Change a value' => 'unolia dns edit www A --value 203.0.113.11',
            'Change the TTL by id' => 'unolia dns edit 88231 --ttl 300',
            'Rename' => 'unolia dns edit old CNAME --name new',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $record = $this->record((string) $this->argumentString('record'), $this->argumentString('type'));
        $zone = $this->zone(Str::scalar(Arr::get($record, 'zone.domain') ?? ($record['zone_domain'] ?? null), '') ?: null, Str::scalar($record['name'] ?? null, ''));
        $id = Str::scalar($record['id'] ?? null, '');

        $name = $this->optionString('name');
        $value = $this->optionString('value');
        $ttl = $this->optionInt('ttl');
        $priority = $this->optionInt('priority');

        if ($name === null && $value === null && $ttl === null && $priority === null) {
            if (! $this->ask()->interactive()) {
                throw CliError::usage('nothing to change', 'Pass --name, --value, --ttl or --priority.');
            }

            $this->out()->note('Editing '.self::describe($record, $zone).'. Enter keeps a value.');
            $name = $this->ask()->text('Name', '--name', '', self::relativeName($record['name'] ?? null, $zone));
            $value = $this->ask()->text('Value', '--value', '', Str::scalar($record['value'] ?? null, ''));
        }

        $changes = Arr::filled([
            'name' => $name === null ? null : self::fullName($name, $zone),
            'value' => $value,
            'ttl' => $ttl,
            'priority' => $priority,
        ]);

        // The API wants the whole record, not a patch of one field.
        $body = array_merge(Arr::filled([
            'name' => $record['name'] ?? null,
            'value' => $record['value'] ?? null,
        ]), $changes);

        if ($this->dryRun()) {
            $this->out()->record(['record' => $id, 'zone' => $zone] + $changes);

            return ExitCode::Ok;
        }

        return $this->propagate($this->fetch(new UpdateRecord($id, $body)), $zone, 'Changed');
    }
}
