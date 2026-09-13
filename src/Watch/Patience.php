<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Poller;

/**
 * Reading a target without hammering the API: a 429 is waited out for the
 * time the server asks, and when a read came back at once the next one
 * waits for the poll interval first, so a long poll that keeps returning
 * early does not turn into a tight loop. The wait sits before the read that
 * follows, never after the one that just happened, so the first reading of
 * anything is on screen as soon as the API answers.
 */
final class Patience
{
    /** How long to wait after a 429 that names no Retry-After. */
    private const BACKOFF = 5;

    /** A read faster than this did not hold the connection, so the interval applies. */
    private const QUICK = 1.0;

    /** When the last quick read finished, if the last read was quick. */
    private ?float $quickAt = null;

    public function __construct(
        private readonly Target $target,
        private readonly Poller $poller,
        private readonly int $interval,
    ) {}

    public function fetch(): TargetState
    {
        if ($this->quickAt !== null) {
            $due = $this->quickAt + $this->interval - microtime(true);

            if ($due > 0) {
                $this->poller->sleep((int) ceil($due));
            }
        }

        while (true) {
            $started = microtime(true);

            try {
                $state = $this->target->fetch();
            } catch (ApiException $exception) {
                if ($exception->status !== 429) {
                    throw $exception;
                }

                $retryAfter = $exception->header('Retry-After');
                $this->poller->sleep(is_numeric($retryAfter) && (int) $retryAfter > 0 ? (int) $retryAfter : self::BACKOFF);

                continue;
            }

            $finished = microtime(true);
            $this->quickAt = $finished - $started < self::QUICK ? $finished : null;

            return $state;
        }
    }
}
