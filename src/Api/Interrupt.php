<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Unolia\Cli\Console\CliError;

/**
 * Ctrl+C while something is followed. Between requests the handler throws at
 * once, which is what ends a sleep. Inside a request PHP only runs the handler
 * once curl returns, and a long poll holds curl for twenty seconds, so there
 * the handler only raises a flag: curl's progress callback sees it within the
 * second and aborts the transfer, and the client throws the interruption in
 * its place.
 */
final class Interrupt
{
    private static bool $armed = false;

    private static bool $inRequest = false;

    private static bool $pending = false;

    public const MESSAGE = 'stopped watching, the remote work keeps running';

    public static function arm(): void
    {
        if (self::$armed || ! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }

        self::$armed = true;
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function (): void {
            if (self::$inRequest) {
                self::$pending = true;

                return;
            }

            throw CliError::interrupted(self::MESSAGE);
        });
    }

    /**
     * Install the handler again after something else took the signal: a
     * Prompts spinner answers Ctrl+C with a bare exit while it runs, which
     * would lose the exit code and the goodbye line.
     */
    public static function rearm(): void
    {
        self::$armed = false;
        self::arm();
    }

    /** Run a request, letting a Ctrl+C during it abort the transfer rather than wait for it. */
    public static function during(callable $request): mixed
    {
        self::$inRequest = true;

        try {
            return $request();
        } finally {
            self::$inRequest = false;
        }
    }

    /** Guzzle's progress option: a truthy return aborts the transfer. */
    public static function progress(): callable
    {
        return static fn (): bool => self::$pending;
    }

    public static function pending(): bool
    {
        return self::$pending;
    }

    public static function throwIfPending(): void
    {
        if (self::$pending) {
            self::$pending = false;

            throw CliError::interrupted(self::MESSAGE);
        }
    }

    /** For tests. */
    public static function reset(): void
    {
        self::$pending = false;
        self::$inRequest = false;
    }

    public static function flag(): void
    {
        self::$pending = true;
    }
}
