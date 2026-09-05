<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Project;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Projects\ListProjects;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'project:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the projects you can reach');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name or domain');
    }

    public function examples(): array
    {
        return [
            'Every project' => 'unolia project list',
            'Active ones' => 'unolia project list --status active',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListProjects($this->listQuery([
            'status' => $this->optionString('status'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'name' => 'Name',
                'domain' => 'Domain',
                'status' => 'Status',
                'websites' => 'Websites',
                'updated_at' => 'Updated',
            ],
            static fn (array $row): array => [
                'status' => Str::scalar($row['status_label'] ?? ($row['status'] ?? null)),
                'websites' => Str::scalar(Arr::get($row, 'counts.websites'), '0'),
                'updated_at' => RelativeTime::ago(is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null),
            ],
            'No projects yet.',
        );

        return ExitCode::Ok;
    }
}
