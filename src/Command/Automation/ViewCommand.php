<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Api\Requests\Automations\ShowAutomation;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ViewCommand extends BaseCommand
{
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one automation and its last runs');
    }

    protected function define(): void
    {
        $this->addArgument('automation', InputArgument::REQUIRED, 'Automation id or exact name');
    }

    public function examples(): array
    {
        return [
            'One automation' => 'unolia automation view 7',
            'By name' => 'unolia automation view "Update Ubuntu servers"',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->automationId((string) $this->argumentString('automation'));
        $automation = $this->fetch(new ShowAutomation($id));
        $runs = $this->collection(new ListAutomationRuns(['automation' => $id, 'per_page' => 5]));

        if ($this->structured()) {
            $this->out()->record($automation + ['runs' => $runs]);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $automation['id'] ?? null,
            'name' => $automation['name'] ?? null,
            'recipe' => Arr::get($automation, 'recipe.name'),
            'state' => $automation['state'] ?? null,
            'cron' => Arr::get($automation, 'triggers.cron'),
            'next_scheduled_at' => RelativeTime::ago(is_string($automation['next_scheduled_at'] ?? null) ? $automation['next_scheduled_at'] : null, null, '-'),
            'url' => $automation['url'] ?? null,
        ], [
            'id' => 'Id',
            'name' => 'Name',
            'recipe' => 'Recipe',
            'state' => 'State',
            'cron' => 'Cron',
            'next_scheduled_at' => 'Next run',
            'url' => 'Url',
        ]);

        if ($runs !== []) {
            $this->out()->line('');
            $this->out()->list(
                $runs,
                ['ulid' => 'Run', 'state' => 'State', 'started_at' => 'Started', 'duration_seconds' => 'Duration'],
                static fn (array $row): array => [
                    'ulid' => Str::limit(is_string($row['ulid'] ?? null) ? $row['ulid'] : '', 10, ''),
                    'started_at' => RelativeTime::ago(is_string($row['started_at'] ?? null) ? $row['started_at'] : null),
                    'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
                ],
            );
        }

        return ExitCode::Ok;
    }
}
