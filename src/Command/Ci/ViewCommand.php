<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ShowAction;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesActions;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    use ResolvesActions;

    protected function canonical(): string
    {
        return 'ci:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one CI run and its jobs');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::OPTIONAL, 'Action id or #run number, the newest by default');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Look at every branch when picking the newest run');
    }

    public function examples(): array
    {
        return [
            'The newest run' => 'unolia ci view',
            'A run number' => 'unolia ci view "#1187"',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $action = $this->fetch(new ShowAction($this->actionId($this->argumentString('run'))));

        if ($this->structured()) {
            $this->out()->record($action);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'run' => '#'.(string) ($action['run_number'] ?? '?'),
            'workflow' => $action['name'] ?? null,
            'branch' => $action['branch'] ?? null,
            'event' => $action['event'] ?? null,
            'status' => $action['status'] ?? null,
            'conclusion' => $action['conclusion'] ?? null,
            'actor' => $action['actor'] ?? null,
            'repository' => Arr::get($action, 'repository.full_name'),
            'started_at' => RelativeTime::ago(is_string($action['started_at'] ?? null) ? $action['started_at'] : null),
            'duration_seconds' => RelativeTime::duration(is_numeric($action['duration_seconds'] ?? null) ? (int) $action['duration_seconds'] : null),
            'html_url' => $action['html_url'] ?? null,
        ], [
            'run' => 'Run',
            'workflow' => 'Workflow',
            'branch' => 'Branch',
            'event' => 'Event',
            'status' => 'Status',
            'conclusion' => 'Conclusion',
            'actor' => 'Actor',
            'repository' => 'Repository',
            'started_at' => 'Started',
            'duration_seconds' => 'Duration',
            'html_url' => 'Url',
        ]);

        $jobs = [];

        foreach (is_array($action['jobs'] ?? null) ? $action['jobs'] : [] as $job) {
            if (is_array($job)) {
                $jobs[] = $job;
            }
        }

        if ($jobs !== []) {
            $this->out()->line('');
            $this->out()->list(
                $jobs,
                ['name' => 'Job', 'status' => 'Status', 'conclusion' => 'Conclusion', 'duration_seconds' => 'Duration'],
                static fn (array $row): array => [
                    'duration_seconds' => RelativeTime::duration(is_numeric($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null),
                ],
            );
        }

        return ExitCode::Ok;
    }
}
