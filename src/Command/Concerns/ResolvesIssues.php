<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Issues\ListIssues;
use Unolia\Cli\Console\CliError;

/**
 * Issue ids are UUIDs. Six characters of prefix are enough to name one.
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
                'an issue prefix needs at least six characters',
                'Run unolia issue list to see them.',
            );
        }

        $rows = $this->collection(new ListIssues(['id_prefix' => $reference, 'state' => 'any', 'per_page' => 10]));
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
            throw CliError::notFound(sprintf('no issue starts with %s', $reference));
        }

        throw CliError::usage(
            sprintf('%s matches several issues', $reference),
            'Use more characters of the id.',
            ['candidates' => array_keys($matches)],
        );
    }
}
