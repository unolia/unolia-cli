<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Issues\ListIssues;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'issue:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the open issues of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every project of the team');
        $this->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Comma list, such as error,warning');
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Filter by state, open by default');
        $this->addOption('fixable', null, InputOption::VALUE_NONE, 'Only issues the CLI can fix');
        $this->addOption('check', null, InputOption::VALUE_REQUIRED, 'Filter by check slug');
        $this->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Only issues about this domain');
    }

    public function examples(): array
    {
        return [
            'Open issues here' => 'unolia issues',
            'What can be fixed' => 'unolia issue list --fixable',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListIssues($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'severity' => $this->optionString('severity'),
            'state' => $this->optionString('state'),
            'fixable' => $this->optionBool('fixable') ? 1 : null,
            'check' => $this->optionString('check'),
            'domain' => $this->optionString('domain'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'severity' => 'Severity',
                'check' => 'Check',
                'concern' => 'Concern',
                'message' => 'Message',
                'fix' => 'Fix',
            ],
            static fn (array $row): array => [
                'id' => substr((string) ($row['id'] ?? ''), 0, 6),
                'concern' => Str::scalar(Arr::get($row, 'concern.name')),
                'message' => Str::limit(is_string($row['message'] ?? null) ? $row['message'] : '', 50),
                'fix' => Arr::get($row, 'fix.available') === true ? (string) (Arr::get($row, 'fix.name') ?? 'yes') : '-',
            ],
            'No open issues here.',
        );

        return ExitCode::Ok;
    }
}
