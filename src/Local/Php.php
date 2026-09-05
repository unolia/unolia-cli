<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * The PHP this directory actually runs on: Herd's isolated binary when there is one.
 */
class Php extends Tool
{
    public function __construct(private readonly Herd $herd) {}

    public function localVersion(string $directory): ?string
    {
        $result = $this->herd->isInstalled()
            ? $this->run('herd', ['php', '-v'], $directory)
            : $this->run('php', ['-v'], $directory);

        if (! $result->successful()) {
            return null;
        }

        return preg_match('/PHP (\d+\.\d+\.\d+)/', $result->output, $matches) === 1 ? $matches[1] : null;
    }

    public static function majorMinor(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        return preg_match('/^(\d+\.\d+)/', $version, $matches) === 1 ? $matches[1] : null;
    }
}
