<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * --jq shells out to an installed jq. Decision 4 of the design: we never bundle one.
 */
final class Jq
{
    public function __construct(private readonly ?string $binary = null) {}

    public function filter(string $json, string $expression): string
    {
        $binary = $this->binary ?? (new ExecutableFinder)->find('jq');

        if ($binary === null) {
            throw CliError::usage(
                'jq is not installed',
                'Install jq, or select fields with --json id,status instead.',
            );
        }

        $process = new Process([$binary, $expression]);
        $process->setInput($json);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw CliError::usage(
                'jq failed: '.trim($process->getErrorOutput() ?: 'unknown error'),
                'Check the expression passed to --jq.',
            );
        }

        return $process->getOutput();
    }
}
