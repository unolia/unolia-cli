<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Repo;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ShowRepository;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'repo:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one repository');
    }

    protected function define(): void
    {
        $this->addArgument('repository', InputArgument::OPTIONAL, 'Repository id or full name');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
    }

    public function examples(): array
    {
        return [
            'The repository of this directory' => 'unolia repo view',
            'Another one' => 'unolia repo view acme/marketing',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $argument = $this->argumentString('repository');

        $id = $argument === null
            ? $this->repositoryId()
            : (ctype_digit($argument) ? (int) $argument : $this->repositoryByName($argument));

        $repository = $this->fetch(new ShowRepository($id));

        if ($this->structured()) {
            $this->out()->record($repository);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $repository['id'] ?? null,
            'full_name' => $repository['full_name'] ?? null,
            'source' => $repository['source'] ?? null,
            'private' => $repository['private'] ?? null,
            'default_branch' => $repository['default_branch'] ?? null,
            'websites_count' => $repository['websites_count'] ?? null,
            'last_pushed_at' => RelativeTime::ago(is_string($repository['last_pushed_at'] ?? null) ? $repository['last_pushed_at'] : null),
            'full_url' => $repository['full_url'] ?? null,
        ], [
            'id' => 'Id',
            'full_name' => 'Repository',
            'source' => 'Source',
            'private' => 'Private',
            'default_branch' => 'Default branch',
            'websites_count' => 'Websites',
            'last_pushed_at' => 'Last push',
            'full_url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
