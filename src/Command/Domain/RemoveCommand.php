<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Domains\DeleteRecord;
use Unolia\Cli\Api\Requests\Domains\ShowRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;

/**
 * Removing a record is not reversible, so it always shows the record first.
 */
final class RemoveCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:remove';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Remove a record');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Remove a record' => 'unolia domain remove 88231',
            'Without asking' => 'unolia domain remove 88231 --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('record');
        $record = $this->fetch(new ShowRecord($id));

        $plan = [
            'id' => $record['id'] ?? $id,
            'name' => $record['name'] ?? null,
            'type' => $record['type'] ?? null,
            'value' => $record['value'] ?? null,
        ];

        if ($this->dryRun()) {
            $this->out()->record($plan + ['dry_run' => true]);

            return ExitCode::Ok;
        }

        $question = sprintf(
            'Remove %s %s %s?',
            (string) ($record['name'] ?? ''),
            (string) ($record['type'] ?? ''),
            (string) ($record['value'] ?? ''),
        );

        if (! $this->confirmOrPlan($question, $plan)) {
            $this->out()->note('Nothing was removed.');

            return ExitCode::Ok;
        }

        $this->api()->send(new DeleteRecord($id));

        if ($this->structured()) {
            $this->out()->record($plan + ['removed' => true]);

            return ExitCode::Ok;
        }

        $this->out()->info('Removed the record');

        return ExitCode::Ok;
    }
}
