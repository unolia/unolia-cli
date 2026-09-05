<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ReplayAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\AutomationRunTarget;

/**
 * Run an automation again with the trigger of an earlier run.
 */
final class ReplayCommand extends BaseCommand
{
    use ResolvesRuns;
    use Watches;

    protected function canonical(): string
    {
        return 'automation:replay';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Replay an automation run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or a prefix of it');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Follow the new run');
        $this->addWatchOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Replay a run' => 'unolia automation replay 01J9A2',
            'Replay and follow' => 'unolia automation replay 01J9A2 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));

        if ($this->dryRun()) {
            $this->out()->record($this->fetch(new ReplayAutomationRun($ulid, ['dry_run' => true])));

            return ExitCode::Ok;
        }

        // A replay does what the original did, so this asks, and a pipe needs --yes.
        if (! $this->ask()->confirm('Replay this run?')) {
            $this->out()->note('Nothing was replayed.');

            return ExitCode::Ok;
        }

        $run = $this->fetch(new ReplayAutomationRun($ulid, ['dry_run' => false]));
        $newUlid = is_string($run['ulid'] ?? null) ? $run['ulid'] : $ulid;

        if (! $this->optionBool('wait')) {
            if ($this->structured()) {
                $this->out()->record($run);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Run %s started', $newUlid));

            return ExitCode::Ok;
        }

        $result = $this->follow(new AutomationRunTarget($this->api(), $newUlid, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
