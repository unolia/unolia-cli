<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

final class Arr
{
    /**
     * Read a dot path out of a nested array.
     *
     * @param  array<mixed>  $array
     */
    public static function get(array $array, string $path, mixed $default = null): mixed
    {
        $value = $array;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $array
     */
    public static function has(array $array, string $path): bool
    {
        $value = $array;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Write a dot path into a nested array.
     *
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    public static function set(array $array, string $path, mixed $value): array
    {
        $segments = explode('.', $path);
        $key = array_shift($segments);

        if ($segments === []) {
            $array[$key] = $value;

            return $array;
        }

        $child = isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
        $array[$key] = self::set($child, implode('.', $segments), $value);

        return $array;
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    public static function forget(array $array, string $key): array
    {
        unset($array[$key]);

        return $array;
    }

    /**
     * Drop null and empty string values, so query strings stay short.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    public static function filled(array $array): array
    {
        return array_filter($array, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * True when the array is a list of rows rather than a single record.
     *
     * @param  array<mixed>  $array
     */
    public static function isList(array $array): bool
    {
        return $array === [] || array_is_list($array);
    }
}
