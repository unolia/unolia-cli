<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Issues\ShowIssue;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesIssues;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    use ResolvesIssues;

    protected function canonical(): string
    {
        return 'issue:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one issue');
    }

    protected function define(): void
    {
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id or a prefix of it');
    }

    public function examples(): array
    {
        return [
            'One issue' => 'unolia issue view 01J9P7',
            'Its fix metadata' => 'unolia issue view 01J9P7 --json fix',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $issue = $this->fetch(new ShowIssue($this->issueId((string) $this->argumentString('issue'))));

        if ($this->structured()) {
            $this->out()->record($issue);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $issue['id'] ?? null,
            'severity' => $issue['severity'] ?? null,
            'state' => $issue['state'] ?? null,
            'check' => $issue['check_title'] ?? ($issue['check'] ?? null),
            'message' => $issue['message'] ?? null,
            'project' => Arr::get($issue, 'project.name'),
            'concern' => Arr::get($issue, 'concern.name'),
            'fix' => Arr::get($issue, 'fix.name') ?? 'none',
            'blast_radius' => Arr::get($issue, 'fix.blast_radius'),
            'reversible' => Arr::get($issue, 'fix.reversible'),
            'first_detected_at' => RelativeTime::ago(is_string($issue['first_detected_at'] ?? null) ? $issue['first_detected_at'] : null),
            'url' => $issue['url'] ?? null,
        ], [
            'id' => 'Id',
            'severity' => 'Severity',
            'state' => 'State',
            'check' => 'Check',
            'message' => 'Message',
            'project' => 'Project',
            'concern' => 'Concern',
            'fix' => 'Fix',
            'blast_radius' => 'Blast radius',
            'reversible' => 'Reversible',
            'first_detected_at' => 'First detected',
            'url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
