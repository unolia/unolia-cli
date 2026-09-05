<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Api\Requests\Automations\ListAutomations;
use Unolia\Cli\Console\CliError;

/**
 * Automation runs are ULIDs. A prefix of six characters or more is enough.
 */
trait ResolvesRuns
{
    protected function runUlid(string $reference): string
    {
        $reference = trim($reference);

        if (strlen($reference) === 26) {
            return strtoupper($reference);
        }

        if (strlen($reference) < 6) {
            throw CliError::usage(
                'a run prefix needs at least six characters',
                'Run unolia automation runs to see them.',
            );
        }

        $rows = $this->collection(new ListAutomationRuns(['ulid_prefix' => $reference, 'per_page' => 10]));
        $matches = [];

        foreach ($rows as $row) {
            if (is_string($row['ulid'] ?? null)) {
                $matches[$row['ulid']] = (string) ($row['state'] ?? '');
            }
        }

        if (count($matches) === 1) {
            return (string) array_key_first($matches);
        }

        if ($matches === []) {
            throw CliError::notFound(sprintf('no run starts with %s', $reference));
        }

        throw CliError::usage(
            sprintf('%s matches several runs', $reference),
            'Use more characters of the ULID.',
            ['candidates' => array_keys($matches)],
        );
    }

    protected function automationId(string $reference): int
    {
        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        $rows = $this->collection(new ListAutomations(['q' => $reference, 'per_page' => 20]));

        foreach ($rows as $row) {
            if (strcasecmp((string) ($row['name'] ?? ''), $reference) === 0 && is_numeric($row['id'] ?? null)) {
                return (int) $row['id'];
            }
        }

        throw CliError::notFound(
            sprintf('no automation called %s', $reference),
            'Run unolia automation list to see them.',
        );
    }
}
