<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\AutomationRunLogs;
use Unolia\Cli\Api\Requests\Automations\ShowAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Watch\AutomationRunTarget;

final class LogsCommand extends BaseCommand
{
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:logs';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Print the log of an automation run');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or a prefix of it');
        $this->addOption('step', null, InputOption::VALUE_REQUIRED, 'Only this step id');
        $this->addOption('follow', 'f', InputOption::VALUE_NONE, 'Keep printing until the run ends');
    }

    public function examples(): array
    {
        return [
            'The log so far' => 'unolia automation logs 01J9A2',
            'Follow one step' => 'unolia automation logs 01J9A2 --step 903 --follow',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));
        $follow = $this->optionBool('follow');
        $after = null;

        while (true) {
            $body = $this->body(new AutomationRunLogs($ulid, array_filter([
                'after' => $after,
                'step' => $this->optionString('step'),
                'wait' => $follow ? 20 : null,
            ], static fn (mixed $value): bool => $value !== null)));

            $lines = [];

            foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $lines[] = $entry;
            }

            if ($this->structured()) {
                foreach ($lines as $entry) {
                    $this->out()->event($entry, '');
                }
            } else {
                foreach ($lines as $entry) {
                    $this->out()->line(sprintf(
                        '%s %s %s',
                        substr((string) ($entry['logged_at'] ?? ''), 11, 8),
                        str_pad((string) ($entry['level'] ?? 'info'), 7),
                        (string) ($entry['message'] ?? ''),
                    ));
                }
            }

            $next = Arr::get($body, 'meta.next_after');
            $after = is_scalar($next) ? (string) $next : $after;

            if (! $follow) {
                return ExitCode::Ok;
            }

            // A quiet poll is what a long poll returns while a step runs, so it
            // is not the end. The run's own state is.
            if ($lines === [] && $this->runHasSettled($ulid)) {
                return ExitCode::Ok;
            }

            $this->runtime()->poller()->sleep(2);
        }
    }

    private function runHasSettled(string $ulid): bool
    {
        $run = $this->fetch(new ShowAutomationRun($ulid));

        return in_array((string) ($run['state'] ?? ''), AutomationRunTarget::TERMINAL, true);
    }
}
