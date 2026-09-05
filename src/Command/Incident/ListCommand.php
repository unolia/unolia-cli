<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Incident;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Incidents\ListIncidents;
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
        return 'incident:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List incidents');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status, open by default');
        $this->addOption('kind', null, InputOption::VALUE_REQUIRED, 'Filter by kind');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only incidents since, such as 7d');
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every project of the team');
    }

    public function examples(): array
    {
        return [
            'Open incidents' => 'unolia incident list',
            'Everything this week' => 'unolia incident list --status any --since 7d',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListIncidents($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'status' => $this->optionString('status'),
            'kind' => $this->optionString('kind'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'kind' => 'Kind',
                'severity' => 'Severity',
                'status' => 'Status',
                'subject' => 'Subject',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
                'origin_deployment' => 'Origin',
            ],
            static fn (array $row): array => [
                'subject' => Str::scalar(Arr::get($row, 'website.domain') ?? Arr::get($row, 'monitor.domain') ?? ($row['domain'] ?? null)),
                'started_at' => RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null),
                'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
                'origin_deployment' => Str::scalar(Arr::get($row, 'origin_deployment.id')),
            ],
            'No incidents.',
        );

        return ExitCode::Ok;
    }
}
