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
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

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
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id, or the six characters unolia issue list prints');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Recheck the issue and wait for the new state, also in a pipe');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Apply the fix and return without rechecking');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'See what it would change' => 'unolia issue fix 8d0e1f --dry-run',
            'Fix it and watch the recheck' => 'unolia issue fix 8d0e1f',
            'Block in a script' => 'unolia issue fix 8d0e1f --yes --wait',
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
        $message = is_string($outcome['message'] ?? null) && $outcome['message'] !== '' ? $outcome['message'] : null;

        // A terminal follows the recheck in a task by default, --no-progress
        // returns as soon as the fix is applied, a pipe rechecks only with --wait.
        $progress = $this->out()->face()->interactive && ! $this->structured() && ! $this->optionBool('no-progress');

        if ($result === 'applied' && $progress) {
            $title = Str::scalar(Arr::get($preview, 'issue.check_title') ?? Arr::get($preview, 'issue.fix.name') ?? Arr::get($preview, 'fix.name'), 'the issue');

            $issue = $this->ask()->task(sprintf('Fixing %s', $title), function (StepLog $log) use ($id, $message): array {
                $log->line('Fix applied'.($message === null ? '' : ': '.$message));
                $log->subLabel('Rechecking');
                $this->api()->send(new RecheckIssue($id, ['dry_run' => false]));
                $issue = $this->waitForRecheck($id);
                $state = Str::scalar($issue['state'] ?? null, 'open');

                $state === 'open'
                    ? $log->warning('Still open after the recheck; DNS may need a moment to propagate')
                    : $log->success(sprintf('Issue %s', $state));

                return $issue;
            });

            return Str::scalar($issue['state'] ?? null, 'open') === 'open' ? ExitCode::RemoteFailure : ExitCode::Ok;
        }

        if ($this->optionBool('wait') && $result === 'applied') {
            $this->api()->send(new RecheckIssue($id, ['dry_run' => false]));
            $outcome['issue'] = $this->waitForRecheck($id);
        }

        if ($this->structured()) {
            $this->out()->record($outcome);
        } else {
            $line = sprintf('Fix %s%s', $result, $message === null ? '' : ': '.$message);
            $result === 'applied' ? $this->out()->info($line) : $this->out()->warn($line);

            if ($result === 'applied' && ! $this->optionBool('wait')) {
                $this->out()->note(sprintf('unolia issue recheck %s asks Unolia to look again.', Str::shortId($id)));
            }
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
