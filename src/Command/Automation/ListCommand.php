<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ListAutomations;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
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
        $this->addOption('state', null, InputOption::VALUE_REQUIRED, 'Filter by state');
        $this->addOption('recipe', null, InputOption::VALUE_REQUIRED, 'Filter by recipe slug');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name');
    }

    public function examples(): array
    {
        return [
            'Every automation' => 'unolia automation list',
            'Active ones' => 'unolia automation list --state active',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListAutomations($this->listQuery([
            'state' => $this->optionString('state'),
            'recipe' => $this->optionString('recipe'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'name' => 'Name',
                'recipe' => 'Recipe',
                'state' => 'State',
                'triggers' => 'Triggers',
                'last_triggered_at' => 'Last run',
                'next_scheduled_at' => 'Next run',
            ],
            static fn (array $row): array => [
                'recipe' => Str::scalar(Arr::get($row, 'recipe.slug')),
                'triggers' => self::triggers($row),
                'last_triggered_at' => RelativeTime::ago(is_string($row['last_triggered_at'] ?? null) ? $row['last_triggered_at'] : null),
                'next_scheduled_at' => RelativeTime::ago(is_string($row['next_scheduled_at'] ?? null) ? $row['next_scheduled_at'] : null, null, '-'),
            ],
            'No automations yet.',
        );

        return ExitCode::Ok;
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
            $parts[] = $cron;
        }

        $events = Arr::get($row, 'triggers.events');

        if (is_array($events) && $events !== []) {
            $parts[] = count($events).' events';
        }

        return $parts === [] ? '-' : implode(', ', $parts);
    }
}
