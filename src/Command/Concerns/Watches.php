<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\ListWebsiteDeployments;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Notifier;
use Unolia\Cli\Watch\DeploymentTarget;
use Unolia\Cli\Watch\Target;
use Unolia\Cli\Watch\Watcher;
use Unolia\Cli\Watch\WatchResult;

/**
 * The flags and the loop shared by every command that follows something to its end.
 */
trait Watches
{
    protected function addWatchOptions(string $defaultTimeout = '15m', int $defaultInterval = 3): void
    {
        $this->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Seconds between checks', (string) $defaultInterval);
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Give up waiting after this long', $defaultTimeout);
        $this->addOption('notify', null, InputOption::VALUE_NONE, 'Send a desktop notification at the end');
    }

    protected function follow(Target $target, ?StepLog $log = null): WatchResult
    {
        $watcher = new Watcher(
            $this->out(),
            $this->runtime()->poller(),
            $this->runtime()->get(Notifier::class),
        );

        return $watcher->run(
            $target,
            $this->duration('interval', 3),
            $this->duration('timeout', 900),
            $this->optionBool('notify'),
            $log,
        );
    }

    /** How long a long poll may hold the connection, always under the request timeout. */
    /**
     * The deployment to follow when none is named: the one running now, or
     * the next one to start. After a push to deploy, that is the deployment
     * the push is about to cause. --last follows the latest one even when it
     * has finished, to read it back.
     */
    protected function pickDeployment(int $website, bool $last): int
    {
        $latest = $this->latestDeploymentOf($website);
        $latestId = $latest['id'];

        if ($latestId !== null && ($last || ! in_array($latest['status'], DeploymentTarget::TERMINAL, true))) {
            return $latestId;
        }

        $domain = $this->fetch(new ShowWebsite($website))['domain'] ?? null;

        $this->out()->note(sprintf(
            'Waiting for the next deployment of %s. Ctrl+C stops watching, the deployment does not.',
            is_string($domain) ? $domain : 'website '.$website,
        ));

        $state = $this->runtime()->poller()->until(
            fn (): array => $this->latestDeploymentOf($website),
            static fn (array $state): bool => $state['id'] !== null && ($latestId === null || $state['id'] > $latestId),
            $this->duration('interval', 3),
            $this->duration('timeout', 900),
        );

        return (int) $state['id'];
    }

    /**
     * @return array{id: ?int, status: ?string}
     */
    private function latestDeploymentOf(int $website): array
    {
        $rows = $this->collection(new ListWebsiteDeployments($website, ['per_page' => 1]));
        $latest = $rows[0] ?? null;

        return [
            'id' => is_numeric($latest['id'] ?? null) ? (int) $latest['id'] : null,
            'status' => is_string($latest['status'] ?? null) ? $latest['status'] : null,
        ];
    }

    protected function waitSeconds(): int
    {
        return min(20, max(2, $this->duration('interval', 3) * 10));
    }
}
