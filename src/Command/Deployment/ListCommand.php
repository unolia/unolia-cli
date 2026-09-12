<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Deployment;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Deployments\ListDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    /** Commit messages are cut here so the row stays on one line. */
    private const MESSAGE_WIDTH = 48;

    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'deployment:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List deployments');
    }

    protected function define(): void
    {
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Filter by branch');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only deployments since, such as 7d');
        $this->addOption('all-websites', null, InputOption::VALUE_NONE, 'Every website of the project, not just the linked one');
    }

    public function examples(): array
    {
        return [
            'Recent deployments here' => 'unolia deployment list',
            'Failures on main' => 'unolia deployment list --status failed --branch main',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $context = $this->context(Need::None);
        $website = $this->optionBool('all-websites') ? null : ($this->optionString('website') !== null ? $this->websiteId() : $context->website);

        $rows = $this->rows(new ListDeployments($this->listQuery([
            'website' => $website,
            'project' => $website === null ? $context->project : null,
            'status' => $this->optionString('status'),
            'branch' => $this->optionString('branch'),
            'since' => $this->optionString('since'),
        ])));

        $this->out()->table($rows, self::table(), 'No deployments yet.');

        return ExitCode::Ok;
    }

    /**
     * Newest first, as the API hands them. The id opens the deployment page,
     * the domain the live site. The state is a glyph, with a word only when
     * the deployment did not succeed. The commit message is what tells a
     * deployment apart from the one before it, so it gets a column, trimmed.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))
                ->dim()
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['status'] ?? null)),
            Column::make('website', 'Website')->cell(static function (array $row): Cell {
                $domain = Arr::get($row, 'website.domain');

                return Cell::text(Str::scalar($domain))->link(is_string($domain) && $domain !== '' ? 'https://'.$domain : null);
            }),
            Column::make('branch', 'Branch')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'commit.branch')))),
            Column::make('commit', 'Commit')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'commit.short')))->dim()),
            Column::make('message', 'Message')->cell(static function (array $row): Cell {
                $message = Arr::get($row, 'commit.message');
                $first = is_string($message) ? trim(strtok($message, "\n") ?: '') : '';

                return Cell::text(mb_strimwidth($first, 0, self::MESSAGE_WIDTH, '…'));
            }),
            Column::make('started_at', 'Started')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null))->dim()),
            Column::make('duration_seconds', 'Took')->right()->cell(static fn (array $row): Cell => Cell::text(RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null))->dim()),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['status'] ?? null, 'success')),
        )
            ->fields([
                'id' => 'Id',
                'website' => 'Website',
                'status' => 'Status',
                'branch' => 'Branch',
                'commit' => 'Commit',
                'started_at' => 'Started',
                'duration_seconds' => 'Duration',
            ])
            ->footer(static fn (int $count): string => $count === 1 ? '1 deployment' : $count.' deployments');
    }
}
