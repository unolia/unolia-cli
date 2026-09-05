<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * What one invocation produced.
 */
final readonly class CliResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    /**
     * @return array<mixed>
     */
    public function json(): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode(trim($this->stdout), true);

        if (! is_array($decoded)) {
            Assert::fail('stdout was not JSON: '.$this->stdout);
        }

        return $decoded;
    }

    /**
     * @return list<array<mixed>>
     */
    public function ndjson(): array
    {
        $rows = [];

        foreach (preg_split('/\R/', trim($this->stdout)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($line, true);

            if (! is_array($decoded)) {
                Assert::fail('a line of stdout was not JSON: '.$line);
            }

            $rows[] = $decoded;
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    public function errorJson(): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode(trim($this->stderr), true);

        if (! is_array($decoded)) {
            Assert::fail('stderr was not JSON: '.$this->stderr);
        }

        return $decoded;
    }
}
