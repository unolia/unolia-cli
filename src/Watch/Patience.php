<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Poller;

/**
 * Reading a target without hammering the API: a 429 is waited out for the
 * time the server asks, and a read that came back at once is followed by the
 * poll interval before the next one, so a long poll that keeps returning
 * early does not turn into a tight loop.
 */
final class Patience
{
    /** How long to wait after a 429 that names no Retry-After. */
    private const BACKOFF = 5;

    /** A read faster than this did not hold the connection, so the interval applies. */
    private const QUICK = 1.0;

    public static function fetch(Target $target, Poller $poller, int $interval): TargetState
    {
        while (true) {
            $started = microtime(true);

            try {
                $state = $target->fetch();
            } catch (ApiException $exception) {
                if ($exception->status !== 429) {
                    throw $exception;
                }

                $retryAfter = $exception->header('Retry-After');
                $poller->sleep(is_numeric($retryAfter) && (int) $retryAfter > 0 ? (int) $retryAfter : self::BACKOFF);

                continue;
            }

            if (microtime(true) - $started < self::QUICK) {
                // The server answered at once: pace the next read ourselves.
                $poller->sleep($interval);
            }

            return $state;
        }
    }
}
