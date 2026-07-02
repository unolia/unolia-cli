<?php

namespace Unolia\UnoliaCLI\Mcp;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Interprets the declarative detection arrays on {@see Agent}: a shell
 * `command` that must succeed, directory `paths` (`~`/`%VAR%`/glob aware),
 * or `files`. Any single match counts as detected.
 */
class AgentDetector
{
    public function detect(Agent $agent, Platform $platform, string $cwd): bool
    {
        return $this->matches($agent->systemDetection($platform), null)
            || $this->matches($agent->projectDetection(), $cwd);
    }

    public function binaryExists(?string $binary, Platform $platform): bool
    {
        if ($binary === null) {
            return false;
        }

        return $this->commandSucceeds(
            $platform === Platform::Windows ? "where {$binary}" : "command -v {$binary}",
        );
    }

    /**
     * @param  array{command?: string, paths?: list<string>, files?: list<string>}  $config
     */
    private function matches(array $config, ?string $basePath): bool
    {
        if (isset($config['command']) && $this->commandSucceeds($config['command'])) {
            return true;
        }

        foreach ($config['paths'] ?? [] as $path) {
            if ($this->directoryExists(Paths::expand($path, $basePath))) {
                return true;
            }
        }

        foreach ($config['files'] ?? [] as $file) {
            if (file_exists(Paths::expand($file, $basePath))) {
                return true;
            }
        }

        return false;
    }

    private function commandSucceeds(string $command): bool
    {
        try {
            return Process::run($command)->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function directoryExists(string $path): bool
    {
        if (! str_contains($path, '*')) {
            return is_dir($path);
        }

        return ! empty(glob($path, GLOB_ONLYDIR));
    }
}
