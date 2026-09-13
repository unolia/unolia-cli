<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class RelativeTime
{
    /** Human readable age of an ISO 8601 timestamp, for the table face. */
    public static function ago(?string $timestamp, ?DateTimeInterface $now = null, string $null = 'never'): string
    {
        $moment = self::parse($timestamp);

        if ($moment === null) {
            return $null;
        }

        $now ??= new DateTimeImmutable('now');
        $seconds = $now->getTimestamp() - $moment->getTimestamp();
        $suffix = $seconds < 0 ? ' from now' : ' ago';
        $seconds = abs($seconds);

        return self::duration($seconds).$suffix;
    }

    /** A compact duration such as 52s, 4m 10s or 2h 5m. */
    /**
     * A full date and time the way a schedule is read, in this machine's zone:
     * "Mon 14 Sep 2026, 11:00 Europe/Paris". When the thing scheduled lives in
     * another zone, its own clock follows in brackets, so a cron written for
     * UTC still makes sense next to it.
     */
    public static function at(?string $timestamp, ?string $timezone = null, string $null = ''): string
    {
        $moment = self::parse($timestamp);

        if ($moment === null) {
            return $null;
        }

        $local = self::localZone();
        $line = $moment->setTimezone($local)->format('D j M Y, H:i').' '.$local->getName();
        $other = self::zone($timezone);

        if ($other !== null && $other->getName() !== $local->getName()) {
            $line .= ' ('.$moment->setTimezone($other)->format('H:i').' '.$other->getName().')';
        }

        return $line;
    }

    /**
     * The zone the person is in: TZ when set, the system clock's zone on a
     * Mac or Linux, PHP's default (often UTC in php.ini) as the last resort.
     */
    public static function localZone(): DateTimeZone
    {
        $candidates = [getenv('TZ') ?: null];
        $link = @readlink('/etc/localtime');

        if (is_string($link) && preg_match('#zoneinfo/(.+)$#', $link, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        $candidates[] = date_default_timezone_get();

        foreach ($candidates as $candidate) {
            $zone = self::zone($candidate);

            if ($zone !== null) {
                return $zone;
            }
        }

        return new DateTimeZone('UTC');
    }

    private static function zone(?string $name): ?DateTimeZone
    {
        if ($name === null || $name === '') {
            return null;
        }

        try {
            return new DateTimeZone($name);
        } catch (\Exception) {
            return null;
        }
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);
            $rest = $seconds % 60;

            return $rest === 0 ? $minutes.'m' : $minutes.'m '.$rest.'s';
        }

        if ($seconds < 86400) {
            $hours = intdiv($seconds, 3600);
            $rest = intdiv($seconds % 3600, 60);

            return $rest === 0 ? $hours.'h' : $hours.'h '.$rest.'m';
        }

        $days = intdiv($seconds, 86400);
        $rest = intdiv($seconds % 86400, 3600);

        return $rest === 0 ? $days.'d' : $days.'d '.$rest.'h';
    }

    public static function parse(?string $timestamp): ?DateTimeImmutable
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($timestamp);
        } catch (Throwable) {
            return null;
        }
    }

    /** Seconds between two ISO 8601 timestamps, null when either is missing. */
    public static function between(?string $from, ?string $to): ?int
    {
        $start = self::parse($from);
        $end = self::parse($to);

        if ($start === null || $end === null) {
            return null;
        }

        return $end->getTimestamp() - $start->getTimestamp();
    }
}
