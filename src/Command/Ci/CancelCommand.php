<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\CancelAction;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesActions;
use Unolia\Cli\Console\ExitCode;

final class CancelCommand extends BaseCommand
{
    use ResolvesActions;

    protected function canonical(): string
    {
        return 'ci:cancel';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Cancel a CI run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::OPTIONAL, 'Action id or #run number, the newest by default');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Look at every branch when picking the newest run');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Cancel the newest run' => 'unolia ci cancel',
            'Cancel a run number' => 'unolia ci cancel "#1187" --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->actionId($this->argumentString('run'));

        if ($this->dryRun()) {
            $this->out()->record($this->fetch(new CancelAction($id, ['dry_run' => true])));

            return ExitCode::Ok;
        }

        if (! $this->confirmOrPlan('Cancel this run?', ['action' => $id])) {
            $this->out()->note('Nothing was cancelled.');

            return ExitCode::Ok;
        }

        $action = $this->fetch(new CancelAction($id, ['dry_run' => false]));

        if ($this->structured()) {
            $this->out()->record($action);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Cancelled #%s', (string) ($action['run_number'] ?? $id)));

        return ExitCode::Ok;
    }
}
