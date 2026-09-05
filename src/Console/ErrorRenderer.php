<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Unolia\Cli\Api\ApiException;

/**
 * One place that turns any failure into a message and an exit code.
 */
final class ErrorRenderer
{
    public static function render(Throwable $throwable, Out $out, OutputInterface $stderr, bool $verbose = false): ExitCode
    {
        $error = self::toCliError($throwable);

        if ($error !== null) {
            $out->error($error);

            if ($verbose) {
                $stderr->writeln(self::trace($throwable), OutputInterface::VERBOSITY_SILENT);
            }

            return $error->exitCode;
        }

        $out->error(new CliError(
            'unexpected_error',
            sprintf('%s: %s', $throwable::class, $throwable->getMessage()),
            ExitCode::RemoteFailure,
            $verbose ? null : 'Run the command again with -v to see the trace.',
        ));

        if ($verbose) {
            $stderr->writeln(self::trace($throwable), OutputInterface::VERBOSITY_SILENT);
        }

        return ExitCode::RemoteFailure;
    }

    /**
     * The trace without its arguments. PHP's own rendering prints the first
     * characters of every string argument unless `zend.exception_ignore_args`
     * is on, and a bearer token is exactly the kind of string that travels as
     * an argument here.
     */
    public static function trace(Throwable $throwable): string
    {
        $lines = [];

        foreach ($throwable->getTrace() as $index => $frame) {
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
            $where = isset($frame['file']) ? sprintf('%s(%d)', $frame['file'], $frame['line'] ?? 0) : '[internal function]';

            $lines[] = sprintf('#%d %s: %s()', $index, $where, $call);
        }

        $lines[] = sprintf('#%d {main}', count($lines));

        return implode("\n", $lines);
    }

    private static function toCliError(Throwable $throwable): ?CliError
    {
        return match (true) {
            $throwable instanceof CliError => $throwable,
            $throwable instanceof ApiException => $throwable->toCliError(),
            $throwable instanceof ConsoleException => CliError::usage(
                lcfirst(rtrim($throwable->getMessage(), '.')),
                'Run unolia help for the command list.',
            ),
            default => null,
        };
    }
}
