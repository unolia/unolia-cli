<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\CreateAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\AutomationRunTarget;

/**
 * Start an automation. --dry-run prints the plan the API computed, and changes nothing.
 */
final class RunCommand extends BaseCommand
{
    use ResolvesRuns;
    use Watches;

    protected function canonical(): string
    {
        return 'automation:run';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Start an automation');
    }

    protected function define(): void
    {
        $this->addArgument('automation', InputArgument::REQUIRED, 'Automation id or exact name');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Follow the run');
        $this->addWatchOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'See the plan' => 'unolia automation run 7 --dry-run',
            'Run and follow' => 'unolia automation run 7 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->automationId((string) $this->argumentString('automation'));

        if ($this->dryRun()) {
            return $this->preview($id);
        }

        // An automation touches servers, so this asks, and a pipe needs --yes.
        if (! $this->ask()->confirm('Run this automation now?')) {
            $this->out()->note('Nothing was started.');

            return ExitCode::Ok;
        }

        $run = $this->fetch(new CreateAutomationRun($id, ['dry_run' => false]));
        $ulid = is_string($run['ulid'] ?? null) ? $run['ulid'] : null;

        if ($ulid === null) {
            $this->out()->record($run);

            return ExitCode::RemoteFailure;
        }

        if (! $this->optionBool('wait')) {
            if ($this->structured()) {
                $this->out()->record($run);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Run %s started · unolia automation watch %s', $ulid, Str::limit($ulid, 10, '')));

            return ExitCode::Ok;
        }

        $result = $this->follow(new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }

    private function preview(int $id): ExitCode
    {
        $plan = $this->fetch(new CreateAutomationRun($id, ['dry_run' => true]));

        if ($this->structured()) {
            $this->out()->record($plan);

            return ExitCode::Ok;
        }

        $steps = [];

        foreach (is_array($plan['steps'] ?? null) ? $plan['steps'] : [] as $step) {
            if (is_array($step)) {
                $steps[] = $step;
            }
        }

        $this->out()->list($steps, ['position' => '#', 'label' => 'Step'], null, 'This recipe has no step.');

        $targets = [];

        foreach (is_array($plan['targets'] ?? null) ? $plan['targets'] : [] as $target) {
            if (is_array($target)) {
                $targets[] = $target;
            }
        }

        if ($targets !== []) {
            $this->out()->line('');
            $this->out()->list($targets, ['type' => 'Type', 'id' => 'Id', 'name' => 'Name']);
        }

        $rejection = $plan['rejection'] ?? null;

        if (is_string($rejection) && $rejection !== '') {
            $this->out()->warn($rejection);

            return ExitCode::RemoteFailure;
        }

        return ExitCode::Ok;
    }
}
