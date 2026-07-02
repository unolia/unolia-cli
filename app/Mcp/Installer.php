<?php

namespace Unolia\UnoliaCLI\Mcp;

use Illuminate\Support\Facades\Process;
use Throwable;
use Unolia\UnoliaCLI\Mcp\Writers\JsonConfigWriter;
use Unolia\UnoliaCLI\Mcp\Writers\TomlConfigWriter;

/**
 * Installs the Unolia MCP server into one agent for one scope: through the
 * agent's own CLI when it has one and the binary is available, otherwise by
 * merging into its config file. Re-runs are idempotent - an existing
 * `unolia` entry is replaced.
 */
class Installer
{
    public function __construct(
        private readonly AgentDetector $detector,
    ) {}

    public function install(Agent $agent, Scope $scope, Platform $platform, string $url, string $cwd): InstallResult
    {
        $shell = $agent->shellCommand($scope, $url);

        if ($shell !== null && $this->detector->binaryExists($agent->binary(), $platform)) {
            return $this->runShell($shell);
        }

        $file = $agent->configFile($scope, $platform);

        if ($file === null) {
            return InstallResult::skipped($agent->unsupportedReason($scope) ?? 'not supported for this scope');
        }

        $path = Paths::expand($file, $scope === Scope::Local ? $cwd : null);

        return $this->writeConfig($agent, $path, $url, $cwd);
    }

    /**
     * @param  list<string>  $command
     */
    private function runShell(array $command): InstallResult
    {
        try {
            $result = Process::run($command);
        } catch (Throwable $e) {
            return InstallResult::failed($e->getMessage());
        }

        // `claude mcp add` exits non-zero when the server already exists;
        // that still means the connector is in place.
        if (! $result->successful() && ! str_contains($result->errorOutput(), 'already exists')) {
            return InstallResult::failed(trim($result->errorOutput()) ?: 'command failed (exit code '.$result->exitCode().')');
        }

        return InstallResult::installed('via `'.implode(' ', array_slice($command, 0, 3)).' …`');
    }

    private function writeConfig(Agent $agent, string $path, string $url, string $cwd): InstallResult
    {
        $written = str_ends_with($path, '.toml')
            ? (new TomlConfigWriter($path))->write($agent->configKey(), Agent::SERVER_KEY, $agent->serverConfig($url))
            : (new JsonConfigWriter($path, $agent->newFileBase()))->write($agent->configKey(), Agent::SERVER_KEY, $agent->serverConfig($url));

        return $written
            ? InstallResult::installed($this->displayPath($path, $cwd))
            : InstallResult::failed($this->displayPath($path, $cwd).' could not be updated safely (comments or invalid JSON?) - add the server manually');
    }

    private function displayPath(string $path, string $cwd): string
    {
        if (str_starts_with($path, rtrim($cwd, '/').'/')) {
            return substr($path, strlen(rtrim($cwd, '/')) + 1);
        }

        $home = Paths::home();

        return str_starts_with($path, $home) ? '~'.substr($path, strlen($home)) : $path;
    }
}
