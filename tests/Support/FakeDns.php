<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Dns;

/**
 * A resolver that answers from a script instead of the network.
 */
final class FakeDns extends Dns
{
    /** @var list<array{domain: string, type: string, server: string}> */
    public array $queries = [];

    /**
     * @param  list<array{name: string, type: string, ttl: int, value: string}>  $answers
     */
    public function __construct(private readonly array $answers = [], private readonly ?string $failure = null, private readonly bool $byType = false)
    {
        parent::__construct();
    }

    public function query(string $domain, string $typeName, int $type, string $server): array
    {
        $this->queries[] = ['domain' => $domain, 'type' => $typeName, 'server' => $server];

        if ($this->failure !== null) {
            throw CliError::remoteFailure(sprintf('%s did not answer: %s', $server, $this->failure));
        }

        // A real resolver only answers the type asked; tests that hand one
        // set of answers to several queries opt in to that with byType.
        return $this->byType
            ? array_values(array_filter($this->answers, static fn (array $row): bool => strcasecmp($row['type'], $typeName) === 0))
            : $this->answers;
    }
}
