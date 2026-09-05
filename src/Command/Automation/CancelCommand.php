<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Automations\CancelAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;

final class CancelCommand extends BaseCommand
{
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:cancel';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Cancel an automation run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or a prefix of it');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Cancel a run' => 'unolia automation cancel 01J9A2',
            'Without asking' => 'unolia automation cancel 01J9A2 --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));

        if ($this->dryRun()) {
            $this->out()->record(['run' => $ulid, 'dry_run' => true]);

            return ExitCode::Ok;
        }

        if (! $this->confirmOrPlan(sprintf('Cancel run %s?', $ulid))) {
            $this->out()->note('Nothing was cancelled.');

            return ExitCode::Ok;
        }

        $run = $this->fetch(new CancelAutomationRun($ulid, ['dry_run' => false]));

        if ($this->structured()) {
            $this->out()->record($run);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Cancelled run %s', $ulid));

        return ExitCode::Ok;
    }
}
