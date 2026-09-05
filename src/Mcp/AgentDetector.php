<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

/**
 * Interprets the detection rules on {@see Agent}: a binary on the PATH, directories
 * (`~`, `%VAR%` and glob aware) or files. Any single match counts.
 */
final class AgentDetector
{
    public function __construct(
        private readonly AgentCli $cli,
        private readonly ConfigPaths $paths,
    ) {}

    public function detect(Agent $agent, Platform $platform, string $cwd): bool
    {
        return $this->matches($agent->systemDetection($platform), null)
            || $this->matches($agent->projectDetection(), $cwd);
    }

    /**
     * @return list<Agent>
     */
    public function detected(Platform $platform, string $cwd): array
    {
        return array_values(array_filter(
            Agent::cases(),
            fn (Agent $agent): bool => $this->detect($agent, $platform, $cwd),
        ));
    }

    /**
     * @param  array{binary?: string, paths?: list<string>, files?: list<string>}  $rules
     */
    private function matches(array $rules, ?string $basePath): bool
    {
        if (isset($rules['binary']) && $this->cli->has($rules['binary'])) {
            return true;
        }

        foreach ($rules['paths'] ?? [] as $path) {
            if ($this->directoryExists($this->paths->expand($path, $basePath))) {
                return true;
            }
        }

        foreach ($rules['files'] ?? [] as $file) {
            if (file_exists($this->paths->expand($file, $basePath))) {
                return true;
            }
        }

        return false;
    }

    private function directoryExists(string $path): bool
    {
        if (! str_contains($path, '*')) {
            return is_dir($path);
        }

        return (glob($path, GLOB_ONLYDIR) ?: []) !== [];
    }
}
