<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * Reads a .env without evaluating it. No interpolation, no side effects.
 */
class DotEnv
{
    /**
     * @return array<string, string>
     */
    public function read(string $directory, string $file = '.env'): array
    {
        $path = rtrim($directory, '/').'/'.$file;

        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        return $this->parse($contents);
    }

    /**
     * @return array<string, string>
     */
    public function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public function keys(string $directory, string $file = '.env'): array
    {
        return array_keys($this->read($directory, $file));
    }
}
