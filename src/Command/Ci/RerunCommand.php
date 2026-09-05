<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\RerunAction;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesActions;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\ActionTarget;

/**
 * Re-run a CI run. It asks first, because a workflow can deploy.
 */
final class RerunCommand extends BaseCommand
{
    use ResolvesActions;
    use Watches;

    protected function canonical(): string
    {
        return 'ci:rerun';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Re-run a CI run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::OPTIONAL, 'Action id or #run number, the newest by default');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Look at every branch when picking the newest run');
        $this->addOption('failed', null, InputOption::VALUE_NONE, 'Only the failed jobs');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Follow the new attempt');
        $this->addWatchOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Re-run the newest run' => 'unolia ci rerun',
            'Only what failed' => 'unolia ci rerun "#1187" --failed --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->actionId($this->argumentString('run'));
        $failedOnly = $this->optionBool('failed');

        if ($this->dryRun()) {
            $this->out()->record($this->fetch(new RerunAction($id, ['dry_run' => true, 'failed_only' => $failedOnly])));

            return ExitCode::Ok;
        }

        // A workflow can deploy, so this asks, and a pipe needs --yes.
        if (! $this->ask()->confirm(sprintf('Re-run %s of this repository?', $failedOnly ? 'the failed jobs' : 'every job'))) {
            $this->out()->note('Nothing was re-run.');

            return ExitCode::Ok;
        }

        $action = $this->fetch(new RerunAction($id, ['dry_run' => false, 'failed_only' => $failedOnly]));
        $newId = is_numeric($action['id'] ?? null) ? (int) $action['id'] : $id;
        $this->local()->remember('last_ci_run', $newId);

        if (! $this->optionBool('wait')) {
            if ($this->structured()) {
                $this->out()->record($action);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Re-running #%s · unolia ci watch', (string) ($action['run_number'] ?? $newId)));

            return ExitCode::Ok;
        }

        $result = $this->follow(new ActionTarget($this->api(), $newId, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
