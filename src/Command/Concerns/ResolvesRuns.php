<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Api\Requests\Automations\ListAutomations;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Str;

/**
 * Automation runs are ULIDs: the first characters are a timestamp shared by
 * every run started around the same time, so the short id every command
 * takes is the tail of the ULID, the six characters unolia automation runs prints.
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
                'a short run id needs at least six characters',
                'Run unolia automation runs to see them.',
            );
        }

        $rows = $this->ask()->spin(
            sprintf('Finding run %s', strtoupper($reference)),
            fn (): array => $this->collection(new ListAutomationRuns(['ulid_suffix' => strtoupper($reference), 'per_page' => 10])),
        );
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
            throw CliError::notFound(
                sprintf('no run ends with %s', $reference),
                'The short id is the last six characters of the ULID, as unolia automation runs prints it.',
            );
        }

        throw CliError::usage(
            sprintf('%s matches several runs', $reference),
            'Use more characters from the end of the ULID, or the whole ULID from unolia automation runs --json ulid.',
            ['candidates' => array_map(Str::shortId(...), array_keys($matches))],
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
