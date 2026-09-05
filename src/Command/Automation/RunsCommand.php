<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class RunsCommand extends BaseCommand
{
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:runs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List automation runs');
    }

    protected function define(): void
    {
        $this->addOption('automation', null, InputOption::VALUE_REQUIRED, 'Only runs of this automation');
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Filter by state');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only runs since, such as 7d');
    }

    public function examples(): array
    {
        return [
            'Recent runs' => 'unolia automation runs',
            'What is stuck' => 'unolia automation runs --state awaiting_input',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $automation = $this->optionString('automation');

        $rows = $this->rows(new ListAutomationRuns($this->listQuery([
            'automation' => $automation === null ? null : $this->automationId($automation),
            'state' => $this->optionString('state'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->list(
            $rows,
            [
                'ulid' => 'Run',
                'automation' => 'Automation',
                'trigger' => 'Trigger',
                'state' => 'State',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
                'summary' => 'Summary',
            ],
            static fn (array $row): array => [
                'ulid' => Str::limit(is_string($row['ulid'] ?? null) ? $row['ulid'] : '', 10, ''),
                'automation' => Str::scalar(Arr::get($row, 'automation.name')),
                'trigger' => Str::scalar(Arr::get($row, 'trigger.kind')),
                'started_at' => RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null),
                'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
                'summary' => Str::limit(is_string($row['summary'] ?? null) ? $row['summary'] : '', 40),
            ],
            'No runs yet.',
        );

        return ExitCode::Ok;
    }
}
