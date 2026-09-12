<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\DeleteRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * Removing a record cannot be undone, so the record is shown and confirmed first.
 */
final class RemoveCommand extends BaseCommand
{
    use ResolvesZones;

    protected function canonical(): string
    {
        return 'dns:remove';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Remove a DNS record');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id, or its name');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type, when the name alone is not enough');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone, the project\'s one by default');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'By name' => 'unolia dns remove old CNAME',
            'By id, without asking' => 'unolia dns remove 88231 --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $record = $this->record((string) $this->argumentString('record'), $this->argumentString('type'));
        $zone = $this->zone(Str::scalar(Arr::get($record, 'zone.domain') ?? ($record['zone_domain'] ?? null), '') ?: null, Str::scalar($record['name'] ?? null, ''));
        $id = Str::scalar($record['id'] ?? null, '');

        $plan = [
            'id' => $record['id'] ?? $id,
            'zone' => $zone,
            'name' => $record['name'] ?? null,
            'type' => $record['type'] ?? null,
            'value' => $record['value'] ?? null,
        ];

        if ($this->dryRun()) {
            $this->out()->record($plan + ['dry_run' => true]);

            return ExitCode::Ok;
        }

        if (! $this->confirmOrPlan(sprintf('Remove %s from %s?', self::describe($record, $zone), $zone))) {
            $this->out()->note('Nothing was removed.');

            return ExitCode::Ok;
        }

        $this->api()->send(new DeleteRecord($id));

        if ($this->structured()) {
            $this->out()->record($plan + ['removed' => true]);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Removed %s from %s', self::describe($record, $zone), $zone));

        return ExitCode::Ok;
    }
}
