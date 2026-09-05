<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Issues\RecheckIssue;
use Unolia\Cli\Api\Requests\Issues\ShowIssue;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesIssues;
use Unolia\Cli\Console\ExitCode;

final class RecheckCommand extends BaseCommand
{
    use ResolvesIssues;

    protected function canonical(): string
    {
        return 'issue:recheck';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Run the check behind an issue again');
    }

    protected function define(): void
    {
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id or a prefix of it');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Wait for the new state');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Queue the check' => 'unolia issue recheck 01J9P7',
            'Wait for the answer' => 'unolia issue recheck 01J9P7 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->issueId((string) $this->argumentString('issue'));

        if ($this->dryRun()) {
            $this->out()->record(['issue' => $id, 'dry_run' => true]);

            return ExitCode::Ok;
        }

        $issue = $this->fetch(new RecheckIssue($id, ['dry_run' => false]));

        if ($this->optionBool('wait')) {
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $issue = $this->fetch(new ShowIssue($id));

                if (($issue['state'] ?? 'open') !== 'open') {
                    break;
                }

                $this->runtime()->poller()->sleep(3);
            }
        }

        if ($this->structured()) {
            $this->out()->record($issue);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Rechecked, the issue is %s', (string) ($issue['state'] ?? 'open')));

        return ExitCode::Ok;
    }
}
