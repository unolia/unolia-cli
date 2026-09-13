<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Automations\ListAutomationRuns;
use Unolia\Cli\Api\Requests\Automations\ListAutomations;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
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

    /** A run that is still going, or waiting: the ones worth following without being named. */
    private const LIVE_RUN = ['pending', 'running', 'awaiting_input', 'failing', 'cancelling'];

    /**
     * The run to follow when none is named: the one going now, or waiting for
     * an answer. Several of them are a choice on a terminal and exit 2 with
     * the candidates in a pipe. None means the last run, to read it back.
     */
    protected function pickRun(): string
    {
        $rows = $this->ask()->spin('Looking for a run', fn (): array => $this->collection(new ListAutomationRuns(['per_page' => 20])));
        $live = [];

        foreach ($rows as $row) {
            if (is_string($row['ulid'] ?? null) && in_array($row['state'] ?? null, self::LIVE_RUN, true)) {
                $live[$row['ulid']] = sprintf(
                    '%s · %s · %s%s',
                    Str::scalar(Arr::get($row, 'automation.name'), 'Automation'),
                    Str::shortId($row['ulid']),
                    str_replace('_', ' ', Str::scalar($row['state'] ?? null, '')),
                    is_string($row['started_at'] ?? null) ? ', started '.RelativeTime::ago($row['started_at']) : '',
                );
            }
        }

        if (count($live) === 1) {
            return (string) array_key_first($live);
        }

        if (count($live) > 1) {
            return $this->ask()->select('Which run?', $live, 'run');
        }

        foreach ($rows as $row) {
            if (is_string($row['ulid'] ?? null)) {
                return $row['ulid'];
            }
        }

        throw CliError::notFound('no run yet', 'unolia automation run <automation> starts one.');
    }

    /**
     * An automation from an id, or from any part of its name: "kernel" finds
     * "Restart servers with pending kernel update". A name that matches
     * whole wins over the rest. Several matches are a choice on a terminal
     * and exit 2 with the candidates in a pipe.
     */
    protected function automationId(string $reference): int
    {
        $reference = trim($reference);

        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        if ($reference === '') {
            throw CliError::usage('name the automation', 'Run unolia automation list to see them.');
        }

        $rows = $this->ask()->spin(
            sprintf('Finding %s', $reference),
            fn (): array => $this->collection(new ListAutomations(['q' => $reference, 'per_page' => 20])),
        );

        $matches = [];

        foreach ($rows as $row) {
            if (! is_numeric($row['id'] ?? null) || ! is_string($row['name'] ?? null)) {
                continue;
            }

            if (strcasecmp($row['name'], $reference) === 0) {
                return (int) $row['id'];
            }

            $recipe = Str::scalar(Arr::get($row, 'recipe.name'), '');
            $matches[(int) $row['id']] = $row['name'].($recipe === '' || strcasecmp($recipe, $row['name']) === 0 ? '' : ' · '.$recipe);
        }

        if (count($matches) === 1) {
            return (int) array_key_first($matches);
        }

        if ($matches === []) {
            throw CliError::notFound(
                sprintf('no automation matches %s', $reference),
                'Run unolia automation list to see them.',
            );
        }

        if ($this->ask()->interactive()) {
            return (int) $this->ask()->select('Which automation?', $matches, 'automation');
        }

        throw CliError::usage(
            sprintf('%s matches several automations', $reference),
            'Use more of the name, or the id from unolia automation list.',
            ['candidates' => array_map(static fn (int $id, string $label): string => sprintf('%d %s', $id, $label), array_keys($matches), $matches)],
        );
    }
}
