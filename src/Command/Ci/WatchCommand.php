<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesActions;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\ActionTarget;

final class WatchCommand extends BaseCommand
{
    use ResolvesActions;
    use Watches;

    protected function canonical(): string
    {
        return 'ci:watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Follow a CI run until it finishes');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::OPTIONAL, 'Action id or #run number, the newest on this branch by default');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Look at every branch when picking the newest run');
        $this->addWatchOptions();
    }

    public function examples(): array
    {
        return [
            'Follow this branch' => 'unolia ci watch',
            'As events' => 'unolia ci watch --format ndjson',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->actionId($this->argumentString('run'));
        $this->local()->remember('last_ci_run', $id);

        $result = $this->follow(new ActionTarget($this->api(), $id, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
