<?php

declare(strict_types=1);

namespace Unolia\Cli\Api;

use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Arr;

/**
 * The loop behind every watch and every --wait. It respects the interval the API asks for,
 * gives up when --timeout elapses, and leaves the remote work running on Ctrl+C.
 */
class Poller
{
    public const MINIMUM_INTERVAL = 2;

    /** @var (callable(int): void)|null */
    private $sleeper;

    /** @var (callable(): int)|null */
    private $clock;

    /**
     * @param  (callable(int): void)|null  $sleeper
     * @param  (callable(): int)|null  $clock
     */
    public function __construct(?callable $sleeper = null, ?callable $clock = null)
    {
        $this->sleeper = $sleeper;
        $this->clock = $clock;
    }

    /**
     * Poll until $isDone says so.
     *
     * @param  callable(): array<string, mixed>  $fetch
     * @param  callable(array<string, mixed>): bool  $isDone
     * @param  (callable(array<string, mixed>, array<string, mixed>|null): void)|null  $onTick
     * @return array<string, mixed>
     */
    public function until(callable $fetch, callable $isDone, int $interval = 3, ?int $timeout = null, ?callable $onTick = null): array
    {
        $interval = max($interval, self::MINIMUM_INTERVAL);
        $started = $this->now();
        $previous = null;

        $this->trap();

        while (true) {
            $state = $fetch();

            if ($onTick !== null) {
                $onTick($state, $previous);
            }

            if ($isDone($state)) {
                return $state;
            }

            if ($timeout !== null && $this->now() - $started >= $timeout) {
                throw CliError::timeout(
                    'gave up waiting, the remote work is still running',
                    'Raise --timeout, or follow it later with unolia watch.',
                );
            }

            $previous = $state;
            $this->sleep($this->intervalFor($state, $interval));
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function intervalFor(array $state, int $fallback): int
    {
        $suggested = Arr::get($state, 'meta.poll_interval');

        if (is_numeric($suggested) && (int) $suggested > 0) {
            return max((int) $suggested, self::MINIMUM_INTERVAL);
        }

        return max($fallback, self::MINIMUM_INTERVAL);
    }

    public function sleep(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if ($this->sleeper !== null) {
            ($this->sleeper)($seconds);

            return;
        }

        sleep($seconds);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    /** Ctrl+C stops the watch, never the deployment. */
    public function trap(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function (): void {
            throw CliError::interrupted('stopped watching, the remote work keeps running');
        });
    }
}
