<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Providers\ListProviders;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'provider:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the connected providers');
    }

    protected function define(): void
    {
        $this->addOption('attention', null, InputOption::VALUE_NONE, 'Only providers that need attention');
    }

    public function examples(): array
    {
        return [
            'Every provider' => 'unolia provider list',
            'What needs attention' => 'unolia provider list --attention',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListProviders($this->listQuery()));

        if ($this->optionBool('attention')) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => ($row['needs_attention'] ?? false) === true));
        }

        $this->out()->table($rows, self::table(), 'No connected providers.');

        return ExitCode::Ok;
    }

    /**
     * By name. A provider that needs attention is amber whatever its state
     * says, and the reasons the API gives are the word for it. The provider
     * kind wears its brand colour, the same as in the website list.
     */
    public static function table(): Table
    {
        $tone = static fn (array $row): mixed => ($row['needs_attention'] ?? false) === true ? 'warning' : ($row['state'] ?? null);

        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($tone($row))),
            Column::make('name', 'Name')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['name'] ?? null))
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('provider', 'Provider')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['provider_label'] ?? ($row['provider'] ?? null)))
                ->color(Tint::provider($row['provider'] ?? null))),
            Column::make('category', 'Category')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['category'] ?? null))->dim()),
            Column::make('synced_at', 'Last synced')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['synced_at'] ?? null) ? $row['synced_at'] : null, null, 'never synced'))->dim()),
            Column::make('state')->cell(static function (array $row): Cell {
                if (($row['needs_attention'] ?? false) !== true) {
                    return Status::word($row['state'] ?? null, 'connected');
                }

                $reasons = $row['attention_reasons'] ?? null;
                $words = is_array($reasons) && $reasons !== [] ? implode(', ', array_map(static fn (mixed $reason): string => str_replace('_', ' ', Str::scalar($reason, '')), $reasons)) : 'needs attention';

                return Cell::text($words)->color('yellow');
            }),
        )
            ->fields([
                'id' => 'Id',
                'name' => 'Name',
                'provider' => 'Provider',
                'category' => 'Category',
                'state' => 'State',
                'synced_at' => 'Last synced',
                'needs_attention' => 'Attention',
            ])
            ->sort(static fn (array $a, array $b): int => strcasecmp(Str::scalar($a['name'] ?? null), Str::scalar($b['name'] ?? null)))
            ->footer(static fn (int $count): string => $count === 1 ? '1 provider' : $count.' providers');
    }
}
