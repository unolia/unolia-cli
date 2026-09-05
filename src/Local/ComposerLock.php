<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * What composer.lock says is installed, without running Composer.
 */
class ComposerLock extends Tool
{
    public function packageVersion(string $directory, string $package): ?string
    {
        $path = rtrim($directory, '/').'/composer.lock';

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $contents === false ? null : json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $decoded[$section] ?? [];

            foreach (is_array($packages) ? $packages : [] as $entry) {
                if (is_array($entry) && ($entry['name'] ?? null) === $package && is_string($entry['version'] ?? null)) {
                    return ltrim($entry['version'], 'v');
                }
            }
        }

        return null;
    }

    public function composerVersion(): ?string
    {
        // The checkout is somebody's repository. Its vendor directory may carry
        // plugins, and asking for a version number is no reason to run them.
        $result = $this->run('composer', ['--version', '--no-ansi', '--no-plugins']);

        if (! $result->successful()) {
            return null;
        }

        return preg_match('/(\d+\.\d+\.\d+)/', $result->output, $matches) === 1 ? $matches[1] : null;
    }
}
