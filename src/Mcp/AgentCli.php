<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

use Symfony\Component\Process\ExecutableFinder;
use Unolia\Cli\Local\ProcessResult;
use Unolia\Cli\Local\Tool;

/**
 * The command line of an AI agent on this machine, for the agents that would rather
 * register a server themselves than have their config file edited.
 */
class AgentCli extends Tool
{
    public function has(string $binary): bool
    {
        return (new ExecutableFinder)->find($binary) !== null;
    }

    /**
     * @param  list<string>  $command  the binary and its arguments
     */
    public function execute(array $command): ProcessResult
    {
        $binary = array_shift($command);

        if ($binary === null) {
            return ProcessResult::missing();
        }

        return $this->run($binary, $command);
    }
}
