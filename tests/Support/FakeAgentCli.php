<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Local\ProcessResult;
use Unolia\Cli\Mcp\AgentCli;

/**
 * Agent binaries that exist only on paper, and a record of what was run through them.
 */
final class FakeAgentCli extends AgentCli
{
    /** @var list<list<string>> */
    public array $ran = [];

    /**
     * @param  list<string>  $binaries  the binaries that count as installed
     */
    public function __construct(
        private readonly array $binaries = [],
        private readonly ?ProcessResult $result = null,
    ) {}

    public function has(string $binary): bool
    {
        return in_array($binary, $this->binaries, true);
    }

    public function execute(array $command): ProcessResult
    {
        $this->ran[] = $command;

        return $this->result ?? new ProcessResult(true, 0);
    }
}
