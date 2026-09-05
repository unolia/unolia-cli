<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Repo;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ListRepositories;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\RelativeTime;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'repo:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the repositories of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every repository of the team');
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Filter by source');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by full name');
    }

    public function examples(): array
    {
        return [
            'Repositories here' => 'unolia repo list',
            'Everywhere' => 'unolia repo list --all-projects',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListRepositories($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'source' => $this->optionString('source'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'full_name' => 'Repository',
                'source' => 'Source',
                'default_branch' => 'Default branch',
                'last_pushed_at' => 'Last push',
                'websites_count' => 'Websites',
            ],
            static fn (array $row): array => [
                'last_pushed_at' => RelativeTime::ago(is_string($row['last_pushed_at'] ?? null) ? $row['last_pushed_at'] : null),
            ],
            'No repositories yet.',
        );

        return ExitCode::Ok;
    }
}
