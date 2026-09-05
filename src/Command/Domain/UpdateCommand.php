<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\ShowRecord;
use Unolia\Cli\Api\Requests\Domains\UpdateRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;

final class UpdateCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:update';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Update a record');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id');
        $this->addArgument('name', InputArgument::OPTIONAL, 'The new record name');
        $this->addArgument('value', InputArgument::OPTIONAL, 'The new record value');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'The new time to live in seconds');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Change a value' => 'unolia domain update 88231 www.acme.com 203.0.113.11',
            'Change the TTL only' => 'unolia domain update 88231 --ttl 300',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('record');
        $current = $this->fetch(new ShowRecord($id));

        $name = $this->argumentString('name');
        $value = $this->argumentString('value');
        $ttl = $this->optionInt('ttl');

        if ($name === null && $value === null && $ttl === null) {
            if (! $this->ask()->interactive()) {
                throw CliError::usage(
                    'nothing to update',
                    'Pass a name, a value or --ttl.',
                );
            }

            $this->out()->note(sprintf(
                'Updating %s %s %s',
                (string) ($current['name'] ?? ''),
                (string) ($current['type'] ?? ''),
                (string) ($current['value'] ?? ''),
            ));

            $name = $this->ask()->text('Full record name', '<name>', '', (string) ($current['name'] ?? ''));
            $value = $this->ask()->text('Value', '<value>', '', (string) ($current['value'] ?? ''));
        }

        $body = Arr::filled([
            'name' => $name ?? ($current['name'] ?? null),
            'value' => $value ?? ($current['value'] ?? null),
            'ttl' => $ttl,
        ]);

        if ($this->dryRun()) {
            $this->out()->record(['record' => $id] + $body);

            return ExitCode::Ok;
        }

        $record = $this->fetch(new UpdateRecord($id, $body));

        if ($this->structured()) {
            $this->out()->record($record);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf(
            'Updated %s %s %s',
            (string) ($record['name'] ?? ''),
            (string) ($record['type'] ?? ''),
            (string) ($record['value'] ?? ''),
        ));

        return ExitCode::Ok;
    }
}
