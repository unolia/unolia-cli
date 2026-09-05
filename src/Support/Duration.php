<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use Unolia\Cli\Console\CliError;

final class Duration
{
    /**
     * Parse a duration such as 90, 90s, 10m or 2h into seconds.
     */
    public static function parse(?string $value, int $default): int
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $value = strtolower(trim($value));

        if (! preg_match('/^(\d+)(s|m|h)?$/', $value, $matches)) {
            throw CliError::usage(
                sprintf('%s is not a duration', $value),
                'Use seconds, or a suffix: 90s, 10m, 2h.',
            );
        }

        $amount = (int) $matches[1];

        return match ($matches[2] ?? 's') {
            'm' => $amount * 60,
            'h' => $amount * 3600,
            default => $amount,
        };
    }

    /**
     * Parse a since window such as 7d or an ISO date, returned as the API expects it.
     */
    public static function since(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
