<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsites;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'website:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the websites of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every website of the team, not just this project');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Filter by provider short name');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by domain or name');
    }

    public function examples(): array
    {
        return [
            'Websites of this project' => 'unolia website list',
            'Every website of the team' => 'unolia website list --all-projects',
            'Domains only' => 'unolia website list --json id,domain',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $project = $this->optionBool('all-projects') ? null : $this->context(Need::None)->project;

        $rows = $this->rows(new ListWebsites($this->listQuery([
            'project' => $project,
            'provider' => $this->optionString('provider'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'domain' => 'Domain',
                'provider' => 'Provider',
                'project' => 'Project',
                'php_version' => 'PHP',
                'last_deployed_at' => 'Last deploy',
                'status' => 'Status',
            ],
            static fn (array $row): array => [
                'provider' => Str::scalar(Arr::get($row, 'provider.label') ?? Arr::get($row, 'provider.slug')),
                'project' => Str::scalar(Arr::get($row, 'project.name')),
                'last_deployed_at' => RelativeTime::ago(is_string($row['last_deployed_at'] ?? null) ? $row['last_deployed_at'] : null),
            ],
            'No websites here yet.',
        );

        return ExitCode::Ok;
    }
}
