<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * A command line tool on the developer's machine. Probes are capped at ten seconds
 * and a missing tool is an answer of "unknown", never an error. A step that does
 * real work (herd init downloading PHP) runs without a cap and can relay its output
 * line by line as it happens.
 */
abstract class Tool
{
    /**
     * @param  list<string>  $arguments
     * @param  (callable(string $line, bool $isError): void)|null  $onLine  receives each output line as it arrives
     * @param  float|null  $timeout  seconds, null for no cap
     */
    protected function run(string $binary, array $arguments, ?string $cwd = null, ?callable $onLine = null, ?float $timeout = 10.0): ProcessResult
    {
        if ((new ExecutableFinder)->find($binary) === null) {
            return ProcessResult::missing();
        }

        $process = new Process([$binary, ...$arguments], $cwd);
        $process->setTimeout($timeout);

        try {
            $process->run($onLine === null ? null : $this->relay($onLine));
        } catch (Throwable $exception) {
            // A probe that fails to run is "unknown"; a step that was
            // started and died (or hit its cap) is a failure with its reason.
            return $onLine === null
                ? ProcessResult::missing()
                : new ProcessResult(true, 1, $process->getOutput(), $exception->getMessage());
        }

        return new ProcessResult(true, $process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
    }

    /**
     * Turn the chunks a process emits into whole lines. Carriage returns
     * (progress bars) count as line ends, so a redraw shows as a new line.
     *
     * @param  callable(string $line, bool $isError): void  $onLine
     * @return callable(string $type, string $buffer): void
     */
    private function relay(callable $onLine): callable
    {
        $pending = [Process::OUT => '', Process::ERR => ''];

        return static function (string $type, string $buffer) use (&$pending, $onLine): void {
            $pending[$type] .= $buffer;
            $parts = preg_split('/\r\n|\r|\n/', $pending[$type]) ?: [];
            $pending[$type] = (string) array_pop($parts);

            foreach ($parts as $line) {
                if (trim($line) !== '') {
                    $onLine(rtrim($line), $type === Process::ERR);
                }
            }
        };
    }
}
