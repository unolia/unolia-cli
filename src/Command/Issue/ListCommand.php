<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Issues\ListIssues;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    /** Messages are cut here so the row stays on one line. */
    private const MESSAGE_WIDTH = 56;

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

        $this->out()->table($rows, self::table(), 'No open issues here.');

        return ExitCode::Ok;
    }

    /**
     * Most severe first, as the API orders them. The issue is named by its
     * short id, the last characters of the uuid, which is what every issue
     * command takes. The severity is a glyph and a word, the fix column names
     * what unolia issue fix would do and stays empty when nothing can.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->cell(static fn (array $row): Cell => Cell::text(Str::shortId($row['id'] ?? null))
                ->dim()
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('glyph')->cell(static fn (array $row): Cell => Status::severity($row['severity'] ?? null)),
            Column::make('severity', 'Severity')->cell(static fn (array $row): Cell => Status::severityWord($row['severity'] ?? null)),
            Column::make('check', 'Check')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['check'] ?? null))->dim()),
            Column::make('concern', 'Concern')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'concern.name')))),
            Column::make('message', 'Message')->cell(static fn (array $row): Cell => Cell::text(Str::limit(is_string($row['message'] ?? null) ? $row['message'] : '', self::MESSAGE_WIDTH))),
            Column::make('fix', 'Fix')->cell(static fn (array $row): Cell => Cell::text(Arr::get($row, 'fix.available') === true ? Str::scalar(Arr::get($row, 'fix.name'), 'yes') : '')),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['state'] ?? null, 'open')),
        )
            ->fields([
                'id' => 'Id',
                'severity' => 'Severity',
                'state' => 'State',
                'check' => 'Check',
                'concern' => 'Concern',
                'message' => 'Message',
                'fix' => 'Fix',
            ])
            ->footer(static fn (int $count): string => $count === 1 ? '1 issue' : $count.' issues');
    }
}
