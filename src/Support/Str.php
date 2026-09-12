<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

final class Str
{
    public static function limit(?string $value, int $length = 40, string $end = '…'): string
    {
        $value ??= '';

        if (mb_strwidth($value) <= $length) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $length, '')).$end;
    }

    /**
     * The short form of a time ordered id (uuid v7, ulid): its last characters,
     * the random part. The first ones are a timestamp shared by everything
     * created around the same time, so a prefix tells nothing apart.
     */
    public static function shortId(mixed $id, int $length = 6): string
    {
        $id = is_scalar($id) ? (string) $id : '';

        return mb_strlen($id) > $length ? mb_substr($id, -$length) : $id;
    }

    public static function headline(string $value): string
    {
        $value = str_replace(['_', '-', '.'], ' ', $value);

        return ucfirst(trim($value));
    }

    /** Turn a canonical command name into the name humans type. */
    public static function display(string $canonical): string
    {
        return str_replace(':', ' ', $canonical);
    }

    public static function stripAnsi(string $value): string
    {
        return (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]|\e\][^\a]*(?:\a|\e\\\\)/', '', $value);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_starts_with($haystack, $needle);
    }

    /** Present a value in a table cell without surprises. */
    public static function scalar(mixed $value, string $null = '-'): string
    {
        return match (true) {
            $value === null => $null,
            is_bool($value) => $value ? 'yes' : 'no',
            is_scalar($value) => (string) $value,
            is_array($value) => self::listOfScalars($value),
            default => $null,
        };
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function listOfScalars(array $value): string
    {
        $parts = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        }

        return implode(', ', $parts);
    }
}
