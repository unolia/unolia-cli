<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\CreateRecord;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Api\Requests\Domains\UpdateRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\PropagatesRecords;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * "Make this name point here": creates the record when there is none of that
 * name and type, replaces the value when there is one, refuses when there are
 * several because it cannot know which one you mean.
 */
final class SetCommand extends BaseCommand
{
    use PropagatesRecords;
    use ResolvesZones;

    protected function canonical(): string
    {
        return 'dns:set';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Create a DNS record, or replace the value of the existing one');
    }

    protected function define(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Record name, relative to the zone: www, @ for the zone itself, or a full hostname');
        $this->addArgument('type', InputArgument::REQUIRED, 'Record type');
        $this->addArgument('value', InputArgument::REQUIRED, 'The value it should have');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone, the project\'s one by default');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Time to live in seconds');
        $this->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority, for MX and SRV');
        $this->addPropagationOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Move www to a new server' => 'unolia dns set www A 203.0.113.11',
            'Set the SPF record' => 'unolia dns set @ TXT "v=spf1 include:_spf.example.com ~all"',
            'In a script' => 'unolia dns set www A 203.0.113.11 --yes --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $name = (string) $this->argumentString('name');
        $type = self::checkType((string) $this->argumentString('type'));
        $value = (string) $this->argumentString('value');
        $zone = $this->zone(null, $name);
        $full = self::fullName($name, $zone);

        $existing = array_values(array_filter(
            $this->collection(new ListDomainRecords($zone, ['name' => $full, 'type' => $type, 'per_page' => 50])),
            static fn (array $row): bool => self::tidy(Str::scalar($row['name'] ?? null, '')) === $full && strcasecmp(Str::scalar($row['type'] ?? null, ''), $type) === 0,
        ));

        $body = Arr::filled([
            'name' => $full,
            'type' => $type,
            'value' => $value,
            'ttl' => $this->optionInt('ttl'),
            'priority' => $this->optionInt('priority'),
        ]);

        if (count($existing) > 1) {
            throw CliError::usage(
                sprintf('%s %s has %d records in %s, so there is no single value to replace', self::relativeName($full, $zone), $type, count($existing), $zone),
                'Edit one by id with unolia dns edit <id> --value, or remove the extra ones first. Ids: '.implode(', ', array_map(static fn (array $row): string => '#'.Str::scalar($row['id'] ?? null), $existing)),
                ['candidates' => array_map(static fn (array $row): string => Str::scalar($row['id'] ?? null), $existing)],
            );
        }

        if ($existing === []) {
            if ($this->dryRun()) {
                $this->out()->record(['zone' => $zone, 'action' => 'create'] + $body);

                return ExitCode::Ok;
            }

            return $this->propagate($this->fetch(new CreateRecord($zone, $body)), $zone, 'Added');
        }

        $current = $existing[0];
        $id = Str::scalar($current['id'] ?? null, '');
        $old = Str::scalar($current['value'] ?? null, '');

        if ($old === $value && ($this->optionInt('ttl') === null || $this->optionInt('ttl') === ($current['ttl'] ?? null))) {
            $this->out()->info(sprintf('%s %s is already %s', self::relativeName($full, $zone), $type, $value));

            return ExitCode::Ok;
        }

        $plan = ['zone' => $zone, 'action' => 'replace', 'record' => $id, 'from' => $old] + $body;

        if (! $this->confirmOrPlan(
            sprintf('%s %s on %s is %s (#%s). Replace it with %s?', self::relativeName($full, $zone), $type, $zone, $old, $id, $value),
            $this->structured() ? [] : ['Record' => '#'.$id, 'From' => $old, 'To' => $value],
        )) {
            if ($this->dryRun()) {
                $this->out()->record($plan);

                return ExitCode::Ok;
            }

            throw CliError::usage('nothing was changed');
        }

        $changes = Arr::filled(['value' => $value, 'ttl' => $this->optionInt('ttl'), 'priority' => $this->optionInt('priority')]);

        return $this->propagate($this->fetch(new UpdateRecord($id, $changes)), $zone, 'Replaced');
    }
}
