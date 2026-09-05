<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Deployments\DeploymentOutput;
use Unolia\Cli\Api\Requests\Deployments\ShowDeployment;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\DeploymentTarget;

/**
 * Printing a deployment log, once or as it arrives.
 */
trait StreamsLogs
{
    protected function printDeploymentOutput(int $deploymentId, bool $follow, bool $raw): void
    {
        $offset = 0;

        while (true) {
            $chunk = $this->fetch(new DeploymentOutput($deploymentId, array_filter([
                'after' => $offset,
                'wait' => $follow ? 20 : null,
            ], static fn (mixed $value): bool => $value !== null)));

            $text = is_string($chunk['chunk'] ?? null) ? $chunk['chunk'] : '';

            if ($text !== '') {
                $this->out()->raw($raw ? $text : Str::stripAnsi($text));
            }

            $next = $chunk['next_offset'] ?? $offset;
            $offset = is_numeric($next) ? (int) $next : $offset;

            if (($chunk['syncing'] ?? false) === true && ! $follow) {
                $this->out()->note('The output is still being fetched from the provider. Run this again in a few seconds, or add --follow to wait for it.');

                return;
            }

            if (! $follow || ($chunk['complete'] ?? false) === true) {
                return;
            }

            if ($this->deploymentIsTerminal($deploymentId) && $text === '') {
                return;
            }

            $this->runtime()->poller()->sleep(2);
        }
    }

    private function deploymentIsTerminal(int $deploymentId): bool
    {
        $deployment = $this->fetch(new ShowDeployment($deploymentId));

        return in_array((string) ($deployment['status'] ?? ''), DeploymentTarget::TERMINAL, true);
    }
}
