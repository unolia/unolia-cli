<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Issues\FixIssue;
use Unolia\Cli\Api\Requests\Issues\RecheckIssue;
use Unolia\Cli\Api\Requests\Issues\ShowIssue;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesIssues;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;

/**
 * Apply the fix an issue carries. The preview always runs first, so nothing changes
 * before the plan has been shown.
 */
final class FixCommand extends BaseCommand
{
    use ResolvesIssues;

    protected function canonical(): string
    {
        return 'issue:fix';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Apply the fix an issue carries');
    }

    protected function define(): void
    {
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id or a prefix of it');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Recheck the issue and wait for the new state');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'See what it would change' => 'unolia issue fix 01J9P7 --dry-run',
            'Fix it and recheck' => 'unolia issue fix 01J9P7 --yes --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->issueId((string) $this->argumentString('issue'));
        $preview = $this->fetch(new FixIssue($id, ['dry_run' => true]));

        if ($this->dryRun()) {
            $this->showPreview($preview);

            return ExitCode::Ok;
        }

        $this->showPreview($preview);

        if (! $this->ask()->confirm('Apply this fix?')) {
            $this->out()->note('Nothing was changed.');

            return ExitCode::Ok;
        }

        $outcome = $this->fetch(new FixIssue($id, ['dry_run' => false]));
        $result = (string) ($outcome['outcome'] ?? 'failed');

        if ($this->optionBool('wait') && $result === 'applied') {
            $this->api()->send(new RecheckIssue($id, ['dry_run' => false]));
            $outcome['issue'] = $this->waitForRecheck($id);
        }

        if ($this->structured()) {
            $this->out()->record($outcome);
        } else {
            $message = $outcome['message'] ?? null;
            $line = sprintf('Fix %s%s', $result, is_string($message) && $message !== '' ? ': '.$message : '');

            $result === 'applied' ? $this->out()->info($line) : $this->out()->warn($line);
        }

        return $result === 'applied' ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function showPreview(array $preview): void
    {
        if ($this->structured()) {
            if ($this->dryRun()) {
                $this->out()->record($preview);
            }

            return;
        }

        $this->out()->record([
            'fix' => Arr::get($preview, 'issue.fix.name') ?? Arr::get($preview, 'fix.name'),
            'blast_radius' => Arr::get($preview, 'issue.fix.blast_radius') ?? Arr::get($preview, 'fix.blast_radius'),
            'reversible' => Arr::get($preview, 'issue.fix.reversible') ?? Arr::get($preview, 'fix.reversible'),
        ], [
            'fix' => 'Fix',
            'blast_radius' => 'Blast radius',
            'reversible' => 'Reversible',
        ]);

        $changes = [];

        foreach (is_array($preview['changes'] ?? null) ? $preview['changes'] : [] as $change) {
            if (is_array($change)) {
                $changes[] = $change;
            }
        }

        if ($changes !== []) {
            $this->out()->line('');
            $this->out()->list($changes, ['op' => 'Change', 'type' => 'Type', 'name' => 'Name', 'value' => 'Value', 'ttl' => 'TTL']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForRecheck(string $id): array
    {
        $issue = [];

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $issue = $this->fetch(new ShowIssue($id));

            if (($issue['state'] ?? 'open') !== 'open') {
                return $issue;
            }

            $this->runtime()->poller()->sleep(3);
        }

        return $issue;
    }
}
