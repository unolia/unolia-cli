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
     * A full date and time in a zone, the way a schedule is read: "Mon 14 Sep 2026, 09:00 Europe/Paris".
     * The zone is the one given, falling back to the machine's; a bad zone name falls back too.
     */
    public static function at(?string $timestamp, ?string $timezone = null, string $null = ''): string
    {
        $moment = self::parse($timestamp);

        if ($moment === null) {
            return $null;
        }

        try {
            $zone = new DateTimeZone($timezone !== null && $timezone !== '' ? $timezone : date_default_timezone_get());
        } catch (\Exception) {
            $zone = new DateTimeZone(date_default_timezone_get());
        }

        return $moment->setTimezone($zone)->format('D j M Y, H:i').' '.$zone->getName();
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
