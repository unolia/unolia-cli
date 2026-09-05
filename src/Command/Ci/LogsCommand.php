<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Ci;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Repositories\ActionJobLog;
use Unolia\Cli\Api\Requests\Repositories\ShowAction;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesActions;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;

/**
 * The log of a CI run, job by job.
 */
final class LogsCommand extends BaseCommand
{
    use ResolvesActions;

    protected function canonical(): string
    {
        return 'ci:logs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print the log of a CI run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::OPTIONAL, 'Action id or #run number, the newest by default');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository id or full name');
        $this->addOption('all-branches', null, InputOption::VALUE_NONE, 'Look at every branch when picking the newest run');
        $this->addOption('job', null, InputOption::VALUE_REQUIRED, 'Only this job, by name or id');
        $this->addOption('failed-steps', null, InputOption::VALUE_NONE, 'Only the jobs that failed');
        $this->addOption('follow', 'f', InputOption::VALUE_NONE, 'Keep printing until the run ends');
        $this->addOption('raw', null, InputOption::VALUE_NONE, 'Keep the ANSI codes');
    }

    public function examples(): array
    {
        return [
            'The whole log' => 'unolia ci logs',
            'One job' => 'unolia ci logs --job tests',
            'What broke' => 'unolia ci logs --failed-steps',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $actionId = $this->actionId($this->argumentString('run'));
        $action = $this->fetch(new ShowAction($actionId));
        $jobs = $this->jobs($action);

        if ($jobs === []) {
            throw CliError::notFound('this run has no job yet', 'Follow it with unolia ci watch.');
        }

        foreach ($jobs as $job) {
            $this->printJob($actionId, $job);
        }

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $action
     * @return list<array<string, mixed>>
     */
    private function jobs(array $action): array
    {
        $wanted = $this->optionString('job');
        $failedOnly = $this->optionBool('failed-steps');
        $jobs = [];

        foreach (is_array($action['jobs'] ?? null) ? $action['jobs'] : [] as $job) {
            if (! is_array($job)) {
                continue;
            }

            if ($wanted !== null
                && (string) ($job['id'] ?? '') !== $wanted
                && strcasecmp((string) ($job['name'] ?? ''), $wanted) !== 0) {
                continue;
            }

            if ($failedOnly && ($job['conclusion'] ?? null) === 'success') {
                continue;
            }

            $jobs[] = $job;
        }

        if ($jobs === [] && $wanted !== null) {
            throw CliError::notFound(sprintf('this run has no job called %s', $wanted));
        }

        return $jobs;
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function printJob(int $actionId, array $job): void
    {
        $jobId = $job['id'] ?? null;

        if (! is_scalar($jobId)) {
            return;
        }

        if (! $this->structured()) {
            $this->out()->note(sprintf('==> %s', (string) ($job['name'] ?? $jobId)));
        }

        $offset = 0;
        $follow = $this->optionBool('follow');

        while (true) {
            $chunk = $this->fetch(new ActionJobLog($actionId, (string) $jobId, array_filter([
                'after' => $offset,
                'wait' => $follow ? 20 : null,
            ], static fn (mixed $value): bool => $value !== null)));

            $text = is_string($chunk['chunk'] ?? null) ? $chunk['chunk'] : '';

            if ($text !== '') {
                $this->out()->raw($this->optionBool('raw') ? $text : Str::stripAnsi($text));
            }

            $next = $chunk['next_offset'] ?? $offset;
            $offset = is_numeric($next) ? (int) $next : $offset;

            if (($chunk['syncing'] ?? false) === true && ! $follow) {
                $this->out()->note('This log is still being fetched from the provider. Run this again in a few seconds, or add --follow to wait for it.');

                return;
            }

            if (! $follow || ($chunk['complete'] ?? false) === true || ($chunk['log_state'] ?? null) === 'missing') {
                return;
            }

            $this->runtime()->poller()->sleep(2);
        }
    }
}
