<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Repositories\ListRepositoryActions;
use Unolia\Cli\Console\CliError;

/**
 * `ci` accepts an action id or a run number written as #1187.
 */
trait ResolvesActions
{
    use ResolvesTargets;

    protected function actionId(?string $reference): int
    {
        if ($reference === null) {
            return $this->newestAction();
        }

        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        if (str_starts_with($reference, '#') && ctype_digit(substr($reference, 1))) {
            return $this->byRunNumber((int) substr($reference, 1));
        }

        throw CliError::usage(
            sprintf('%s is not a run', $reference),
            'Pass the action id, or a run number such as #1187.',
        );
    }

    protected function newestAction(): int
    {
        $branch = $this->optionBool('all-branches') ? null : $this->runtime()->context()->git()->branch();

        $rows = $this->collection(new ListRepositoryActions($this->repositoryId(), array_filter([
            'branch' => $branch,
            'per_page' => 1,
        ], static fn (mixed $value): bool => $value !== null)));

        $latest = $rows[0] ?? null;

        if ($latest === null || ! is_numeric($latest['id'] ?? null)) {
            throw CliError::notFound(
                $branch === null ? 'this repository has no run yet' : sprintf('no run on %s yet', $branch),
                'Pass a run explicitly, or add --all-branches.',
            );
        }

        return (int) $latest['id'];
    }

    private function byRunNumber(int $runNumber): int
    {
        $rows = $this->collection(new ListRepositoryActions($this->repositoryId(), [
            'run_number' => $runNumber,
            'per_page' => 1,
        ]));

        $match = $rows[0] ?? null;

        if ($match === null || ! is_numeric($match['id'] ?? null)) {
            throw CliError::notFound(sprintf('no run #%d on this repository', $runNumber));
        }

        return (int) $match['id'];
    }
}
