<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\CreateDeployment;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Arr;
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
            $website = $this->fetch(new ShowWebsite($websiteId));

            $confirmed = $this->ask()->confirm(sprintf(
                'Deploy %s from %s at %s?',
                (string) ($website['domain'] ?? $websiteId),
                (string) ($website['repository']['branch'] ?? 'the default branch'),
                (string) ($website['deployed_commit'] ?? 'the latest commit'),
            ));

            if (! $confirmed) {
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
            $this->out()->record($preview, [
                'website_id' => 'Website',
                'supports_deployments' => 'Supports deployments',
                'would_trigger' => 'Would trigger',
                'summary' => 'Summary',
            ]);
        }

        return ($preview['would_trigger'] ?? false) === true ? ExitCode::Ok : ExitCode::RemoteFailure;
    }
}
