<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Issues\IgnoreIssue;
use Unolia\Cli\Api\Requests\Issues\ShowIssue;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesIssues;
use Unolia\Cli\Console\ExitCode;

final class IgnoreCommand extends BaseCommand
{
    use ResolvesIssues;

    protected function canonical(): string
    {
        return 'issue:ignore';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Ignore an issue');
    }

    protected function define(): void
    {
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id or a prefix of it');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Ignore an issue' => 'unolia issue ignore 01J9P7',
            'Without asking' => 'unolia issue ignore 01J9P7 --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->issueId((string) $this->argumentString('issue'));
        $issue = $this->fetch(new ShowIssue($id));

        $plan = [
            'id' => $issue['id'] ?? $id,
            'check' => $issue['check_title'] ?? ($issue['check'] ?? null),
            'message' => $issue['message'] ?? null,
        ];

        if ($this->dryRun()) {
            $this->out()->record($plan + ['dry_run' => true]);

            return ExitCode::Ok;
        }

        if (! $this->confirmOrPlan('Ignore this issue?', $plan)) {
            $this->out()->note('Nothing was ignored.');

            return ExitCode::Ok;
        }

        $ignored = $this->fetch(new IgnoreIssue($id, ['dry_run' => false]));

        if ($this->structured()) {
            $this->out()->record($ignored);

            return ExitCode::Ok;
        }

        $this->out()->info('Ignored the issue');

        return ExitCode::Ok;
    }
}
