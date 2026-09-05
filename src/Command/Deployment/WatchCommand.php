<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Deployment;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\DeploymentTarget;

final class WatchCommand extends BaseCommand
{
    use Watches;

    protected function canonical(): string
    {
        return 'deployment:watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Follow a deployment until it finishes');
    }

    protected function define(): void
    {
        $this->addArgument('deployment', InputArgument::REQUIRED, 'Deployment id');
        $this->addWatchOptions();
    }

    public function examples(): array
    {
        return [
            'Follow a deployment' => 'unolia deployment watch 4812',
            'As a stream of events' => 'unolia watch deployment 4812 --format ndjson',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('deployment');

        if (! ctype_digit($id)) {
            throw CliError::usage('the deployment id must be a number');
        }

        $this->local()->remember('last_deployment', (int) $id);

        $result = $this->follow(new DeploymentTarget($this->api(), (int) $id, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
