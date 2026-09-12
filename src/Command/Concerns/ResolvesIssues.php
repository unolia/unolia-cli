<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Issues\ListIssues;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Str;

/**
 * Issue ids are time ordered uuids: the first characters are a timestamp
 * shared by every issue found around the same time, so the short id every
 * command takes is the tail of the uuid, the six characters the list prints.
 */
trait ResolvesIssues
{
    protected function issueId(string $reference): string
    {
        $reference = trim($reference);

        if (strlen($reference) >= 26) {
            return $reference;
        }

        if (strlen($reference) < 6) {
            throw CliError::usage(
                'a short issue id needs at least six characters',
                'Run unolia issue list to see them.',
            );
        }

        $rows = $this->collection(new ListIssues(['id_suffix' => strtolower($reference), 'state' => 'any', 'per_page' => 10]));
        $matches = [];

        foreach ($rows as $row) {
            if (is_string($row['id'] ?? null)) {
                $matches[$row['id']] = (string) ($row['message'] ?? '');
            }
        }

        if (count($matches) === 1) {
            return (string) array_key_first($matches);
        }

        if ($matches === []) {
            throw CliError::notFound(
                sprintf('no issue ends with %s', $reference),
                'The short id is the last six characters of the id, as unolia issue list prints it.',
            );
        }

        throw CliError::usage(
            sprintf('%s matches several issues', $reference),
            'Use more characters from the end of the id, or the whole id from unolia issue list --json id.',
            ['candidates' => array_map(Str::shortId(...), array_keys($matches))],
        );
    }
}
