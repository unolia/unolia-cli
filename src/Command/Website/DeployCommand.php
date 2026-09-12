<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\CreateDeployment;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\DeploymentTarget;
use Unolia\Cli\Watch\WatchResult;

/**
 * Deploy a website through its provider, and optionally wait for the result.
 */
class DeployCommand extends BaseCommand
{
    use ResolvesTargets;
    use Watches;

    protected function canonical(): string
    {
        return 'website:deploy';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Deploy a website and optionally wait for the result');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Block until the deployment finishes, also in a pipe');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Return as soon as the deployment is queued instead of following it');
        $this->addWatchOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Deploy this directory and watch it' => 'unolia deploy',
            'Queue it and come back later' => 'unolia deploy --no-progress',
            'Block in a script' => 'unolia deploy --wait --yes',
            'Stream the steps to a script' => 'unolia website deploy 118 --wait --format ndjson',
        ];
    }

    /** Which website this invocation deploys. The top level `deploy` maps an environment name here. */
    protected function targetWebsiteId(): int
    {
        return $this->websiteId();
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $websiteId = $this->targetWebsiteId();

        if ($this->dryRun()) {
            return $this->preview($websiteId);
        }

        // A production deploy always asks. On a terminal that is a prompt; in
        // a pipe the question is refused with exit 2 unless --yes was passed,
        // which is what keeps an agent from deploying by accident.
        if (! $this->yes()) {
            // The question is built from the same preview --dry-run prints,
            // so it names what production runs now and what it would get.
            $preview = $this->fetch(new CreateDeployment($websiteId, ['dry_run' => true]));

            if (($preview['would_trigger'] ?? false) !== true) {
                throw CliError::remoteFailure(Str::scalar($preview['summary'] ?? null, 'this website cannot be deployed from Unolia'));
            }

            if (! $this->ask()->confirm(self::question($preview))) {
                throw CliError::usage('nothing was deployed');
            }
        }

        $deployment = $this->fetch(new CreateDeployment($websiteId, ['dry_run' => false]));
        $id = $deployment['id'] ?? null;

        if (! is_numeric($id)) {
            throw CliError::remoteFailure('the API did not return a deployment');
        }

        $id = (int) $id;
        $this->local()->remember('last_deployment', $id);

        // On a terminal the deployment is followed in a task by default, the
        // way herd init is: its output scrolls under the label and the outcome
        // stays. --no-progress hands the id back at once. A pipe only waits
        // when told to.
        $progress = $this->out()->face()->interactive && ! $this->structured() && ! $this->optionBool('no-progress');

        if (! $this->optionBool('wait') && ! $progress) {
            if ($this->structured()) {
                $this->out()->record($deployment);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Deployment %d started · unolia watch deployment %d', $id, $id));

            return ExitCode::Ok;
        }

        $target = new DeploymentTarget($this->api(), $id, $this->waitSeconds());

        if ($progress) {
            $domain = Arr::get($deployment, 'website.domain');
            $label = sprintf('Deploying %s', is_string($domain) && $domain !== '' ? $domain : 'website '.$websiteId);

            $result = $this->ask()->task($label, fn (StepLog $log): WatchResult => $this->follow($target, $log));

            if ($result->exitCode !== ExitCode::Ok) {
                $this->out()->note(sprintf('unolia deployment logs %d shows the whole output.', $id));
            }

            return $result->exitCode;
        }

        $result = $this->follow($target);

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }

    private function preview(int $websiteId): ExitCode
    {
        $preview = $this->fetch(new CreateDeployment($websiteId, ['dry_run' => true]));

        if ($this->structured()) {
            $this->out()->record($preview);
        } else {
            $this->out()->record(self::previewLines($preview));
        }

        return ($preview['would_trigger'] ?? false) === true ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    /**
     * The preview as a person reads it: what is live, what the branch holds,
     * how far apart they are. Each line is skipped when the API does not know.
     *
     * @param  array<string, mixed>  $preview
     * @return array<string, string>
     */
    public static function previewLines(array $preview): array
    {
        $lines = [];
        $branch = Arr::get($preview, 'website.branch');
        $website = array_filter([
            Str::scalar(Arr::get($preview, 'website.domain'), ''),
            Str::scalar(Arr::get($preview, 'website.provider.label'), ''),
            is_string($branch) ? $branch : '',
        ], static fn (string $part): bool => $part !== '');
        $lines['Website'] = $website === [] ? 'website #'.Str::scalar($preview['website_id'] ?? null) : implode(' · ', $website);

        $repository = Arr::get($preview, 'repository.full_name');

        if (is_string($repository) && $repository !== '') {
            $lines['Repository'] = $repository;
        }

        $current = Arr::get($preview, 'current');
        $lines['Live now'] = is_array($current) ? self::commitLine($current['commit'] ?? null, $current['ended_at'] ?? $current['started_at'] ?? null, $current['status'] ?? null) : 'no deployment yet';

        $head = Arr::get($preview, 'head');

        if (is_array($head)) {
            $lines['Branch head'] = self::commitLine($head, $head['committed_at'] ?? null, null);
        }

        $pending = $preview['pending_commits'] ?? null;

        if (($preview['up_to_date'] ?? null) === true) {
            $lines['Pending'] = 'nothing new, the live site is at the branch head';
        } elseif (is_numeric($pending)) {
            $lines['Pending'] = (int) $pending === 1 ? '1 new commit' : $pending.' new commits';
        }

        $lines['Dry run'] = ($preview['would_trigger'] ?? false) === true
            ? 'nothing was triggered; unolia deploy would deploy '.Str::scalar(Arr::get($preview, 'website.domain'), 'this website').(is_string($branch) ? ' from '.$branch : '')
            : Str::scalar($preview['summary'] ?? null, 'this website cannot be deployed from Unolia');

        return $lines;
    }

    /**
     * The confirmation, from the preview: the commit range when it is known,
     * a plain question otherwise.
     *
     * @param  array<string, mixed>  $preview
     */
    public static function question(array $preview): string
    {
        $domain = Str::scalar(Arr::get($preview, 'website.domain'), 'website #'.Str::scalar($preview['website_id'] ?? null));
        $branch = Arr::get($preview, 'website.branch');
        $from = Arr::get($preview, 'current.commit.short');
        $to = Arr::get($preview, 'head.short');
        $pending = $preview['pending_commits'] ?? null;

        if (($preview['up_to_date'] ?? null) === true && is_string($to)) {
            return sprintf('%s is already at %s. Deploy it again?', $domain, $to);
        }

        $question = sprintf('Deploy %s%s', $domain, is_string($branch) && $branch !== '' ? ' from '.$branch : '');

        if (is_string($from) && is_string($to)) {
            $question .= sprintf(', %s to %s', $from, $to);

            if (is_numeric($pending) && (int) $pending > 0) {
                $question .= (int) $pending === 1 ? ' (1 new commit)' : sprintf(' (%d new commits)', (int) $pending);
            }
        } elseif (is_string($to)) {
            $question .= ' at '.$to;
        }

        return $question.'?';
    }

    /**
     * "3f9c2e1 Fix the footer · eser · 2 days ago", with the status when the
     * deployment did not succeed.
     *
     * @param  array<string, mixed>|null  $commit
     */
    private static function commitLine(mixed $commit, mixed $when, mixed $status): string
    {
        $commit = is_array($commit) ? $commit : [];
        $parts = array_filter([
            trim(Str::scalar($commit['short'] ?? null, '').' '.Str::limit(is_string($commit['message'] ?? null) ? $commit['message'] : '', 60)),
            Str::scalar($commit['author'] ?? null, ''),
            RelativeTime::ago(is_string($when) ? $when : null, null, ''),
        ], static fn (string $part): bool => $part !== '');

        $line = $parts === [] ? 'unknown commit' : implode(' · ', $parts);

        return is_string($status) && $status !== '' && $status !== 'success' ? $line.' ('.str_replace('_', ' ', $status).')' : $line;
    }
}
