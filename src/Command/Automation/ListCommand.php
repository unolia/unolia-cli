<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Lorisleiva\CronTranslator\CronTranslator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ListAutomations;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'automation:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the automations of this team');
    }

    protected function define(): void
    {
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Filter by state; every state but archived by default, any for all');
        $this->addOption('recipe', null, InputOption::VALUE_REQUIRED, 'Filter by recipe slug');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name');
    }

    public function examples(): array
    {
        return [
            'Every automation but the archived' => 'unolia automation list',
            'The archived ones too' => 'unolia automation list --state any',
            'Paused ones' => 'unolia automation list --state paused',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListAutomations($this->listQuery([
            'state' => $this->optionString('state'),
            'recipe' => $this->optionString('recipe'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->table($rows, self::table(), 'No automations yet.');

        return ExitCode::Ok;
    }

    /**
     * Newest first. The name opens the automation page, the recipe is dim
     * because the name usually says the same, the state is a glyph with a
     * word only when the automation is not ready to run.
     */
    public static function table(): Table
    {
        return Table::make(
            Column::make('id', 'Id')->right()->cell(static fn (array $row): Cell => Cell::text('#'.Str::scalar($row['id'] ?? null))->dim()),
            Column::make('status')->cell(static fn (array $row): Cell => Status::glyph($row['state'] ?? null)),
            Column::make('name', 'Automation')->cell(static fn (array $row): Cell => Cell::text(Str::scalar($row['name'] ?? null))
                ->link(is_string($row['url'] ?? null) ? $row['url'] : null)),
            Column::make('recipe', 'Recipe')->cell(static fn (array $row): Cell => Cell::text(Str::scalar(Arr::get($row, 'recipe.slug')))->dim()),
            Column::make('triggers', 'Triggers')->cell(static fn (array $row): Cell => Cell::text(self::triggers($row))),
            Column::make('last_triggered_at', 'Last run')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::ago(is_string($row['last_triggered_at'] ?? null) ? $row['last_triggered_at'] : null))->dim()),
            Column::make('next_scheduled_at', 'Next run')->cell(static fn (array $row): Cell => Cell::text(RelativeTime::at(
                is_string($row['next_scheduled_at'] ?? null) ? $row['next_scheduled_at'] : null,
                is_string(Arr::get($row, 'triggers.timezone')) ? Arr::get($row, 'triggers.timezone') : null,
            ))),
            Column::make('state')->cell(static fn (array $row): Cell => Status::word($row['state'] ?? null, 'ready')),
        )
            ->fields([
                'id' => 'Id',
                'name' => 'Name',
                'recipe' => 'Recipe',
                'state' => 'State',
                'triggers' => 'Triggers',
                'last_triggered_at' => 'Last run',
                'next_scheduled_at' => 'Next run',
            ])
            ->sort(static fn (array $a, array $b): int => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0))
            ->footer(static fn (int $count): string => $count === 1 ? '1 automation' : $count.' automations');
    }

    /** "0 4 * * 1" as "every Monday at 4:00am"; the expression itself when it cannot be read. */
    private static function cron(string $expression): string
    {
        try {
            return lcfirst(CronTranslator::translate($expression));
        } catch (\Throwable) {
            return $expression;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function triggers(array $row): string
    {
        $parts = [];

        if (Arr::get($row, 'triggers.manual') === true) {
            $parts[] = 'manual';
        }

        $cron = Arr::get($row, 'triggers.cron');

        if (is_string($cron) && $cron !== '') {
            $parts[] = self::cron($cron);
        }

        $events = Arr::get($row, 'triggers.events');

        if (is_array($events) && $events !== []) {
            $parts[] = count($events) === 1 ? '1 event' : count($events).' events';
        }

        return implode(', ', $parts);
    }
}
