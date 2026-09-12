<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Team;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Core\ListTeams;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'team:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the teams this token can reach');
    }

    public function examples(): array
    {
        return [
            'Every team' => 'unolia team list',
            'Slugs only' => 'unolia teams --json slug',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListTeams($this->listQuery()));
        $current = $this->runtime()->context()->teamSlug();

        $this->out()->table($rows, self::table($current), 'This token reaches no team.');

        return ExitCode::Ok;
    }

    /**
     * By name. The team this directory works in is marked with a green dot
     * and shown in bold; a pipe gets a star in the same column.
     */
    public static function table(?string $current): Table
    {
        $isCurrent = static fn (array $row): bool => $current !== null && in_array($current, [(string) ($row['slug'] ?? ''), (string) ($row['id'] ?? '')], true);

        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('current')->cell(static fn (array $row): Cell => $isCurrent($row) ? Cell::text('●')->color('green')->plain('*') : Cell::empty()),
            Column::make('slug', 'Slug')->cell(static fn (array $row): Cell => $isCurrent($row) ? Cell::text(Str::scalar($row['slug'] ?? null))->bold() : Cell::text(Str::scalar($row['slug'] ?? null))),
            Column::make('name', 'Name')->cell(static fn (array $row): Cell => $isCurrent($row) ? Cell::text(Str::scalar($row['name'] ?? null))->bold() : Cell::text(Str::scalar($row['name'] ?? null))),
        )
            ->fields(['id' => 'Id', 'slug' => 'Slug', 'name' => 'Name'])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['name'] ?? null), Str::scalar($b['name'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 team' : $count.' teams');
    }
}
