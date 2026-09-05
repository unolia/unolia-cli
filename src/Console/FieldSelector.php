<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Unolia\Cli\Support\Arr;

/**
 * Implements --json a,b.c: keeps only the named dot paths, rebuilt as nested objects.
 */
final class FieldSelector
{
    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function apply(array $data, ?string $fields): array
    {
        $paths = self::paths($fields);

        if ($paths === []) {
            return $data;
        }

        if (Arr::isList($data)) {
            return array_map(
                static fn (mixed $row): mixed => is_array($row) ? self::pick($row, $paths) : $row,
                $data,
            );
        }

        return self::pick($data, $paths);
    }

    /**
     * @return list<string>
     */
    public static function paths(?string $fields): array
    {
        if ($fields === null || trim($fields) === '') {
            return [];
        }

        $paths = [];

        foreach (explode(',', $fields) as $path) {
            $path = trim($path);

            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param  array<mixed>  $row
     * @param  list<string>  $paths
     * @return array<mixed>
     */
    private static function pick(array $row, array $paths): array
    {
        $picked = [];

        foreach ($paths as $path) {
            if (Arr::has($row, $path)) {
                $picked = Arr::set($picked, $path, Arr::get($row, $path));
            }
        }

        return $picked;
    }
}
