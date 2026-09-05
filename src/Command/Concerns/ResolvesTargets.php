<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Repositories\ListRepositories;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Context\Need;

/**
 * Turning "the thing this command acts on" into an id, from an argument, a flag or the
 * directory, in that order.
 */
trait ResolvesTargets
{
    protected function websiteId(?string $argument = null): int
    {
        $argument ??= $this->argumentString('website');

        if ($argument !== null) {
            return $this->runtime()->context()->websiteId($argument);
        }

        return $this->context(Need::Website)->requireWebsite();
    }

    protected function projectId(?string $argument = null): int
    {
        $argument ??= $this->argumentString('project');

        if ($argument !== null) {
            return $this->runtime()->context()->projectId($argument);
        }

        return $this->context(Need::Project)->requireProject();
    }

    /**
     * The repository behind this directory: --repo, else the linked website's repository.
     */
    protected function repositoryId(): int
    {
        $repo = $this->optionString('repo');

        if ($repo !== null && ctype_digit($repo)) {
            return (int) $repo;
        }

        if ($repo !== null) {
            return $this->repositoryByName($repo);
        }

        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $id = $website['repository']['id'] ?? null;

        if (! is_numeric($id)) {
            throw CliError::notFound(
                'this website has no repository',
                'Pass --repo <id or owner/name>.',
            );
        }

        return (int) $id;
    }

    private function repositoryByName(string $fullName): int
    {
        $rows = $this->collection(new ListRepositories(['q' => $fullName, 'per_page' => 20]));

        foreach ($rows as $row) {
            if (($row['full_name'] ?? null) === $fullName && is_numeric($row['id'] ?? null)) {
                return (int) $row['id'];
            }
        }

        throw CliError::notFound(
            sprintf('no repository called %s', $fullName),
            'Run unolia repo list to see the ones you can reach.',
        );
    }
}
