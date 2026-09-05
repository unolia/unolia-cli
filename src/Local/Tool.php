<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * A command line tool on the developer's machine. Ten second cap, and a missing tool
 * is an answer of "unknown", never an error.
 */
abstract class Tool
{
    /**
     * @param  list<string>  $arguments
     */
    protected function run(string $binary, array $arguments, ?string $cwd = null): ProcessResult
    {
        if ((new ExecutableFinder)->find($binary) === null) {
            return ProcessResult::missing();
        }

        $process = new Process([$binary, ...$arguments], $cwd);
        $process->setTimeout(10);

        try {
            $process->run();
        } catch (Throwable) {
            return ProcessResult::missing();
        }

        return new ProcessResult(true, $process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
    }
}
