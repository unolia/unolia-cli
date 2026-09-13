<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\FollowsAutomationRuns;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\AutomationRunTarget;

final class WatchCommand extends BaseCommand
{
    use FollowsAutomationRuns;
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Follow an automation run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or its short id, the last six characters');
        $this->addWatchOptions();
    }

    public function examples(): array
    {
        return [
            'Follow a run' => 'unolia automation watch PC0XCA',
            'As events' => 'unolia automation watch PC0XCA --format ndjson',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));

        // A terminal reads the run as one task per step, and answers a
        // question where the run stopped to ask it.
        if ($this->out()->face()->interactive && ! $this->structured()) {
            return $this->followRunSteps($ulid);
        }

        $result = $this->follow(new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
