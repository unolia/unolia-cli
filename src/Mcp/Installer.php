<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

use Unolia\Cli\Config\Paths;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Mcp\Writers\JsonConfigWriter;
use Unolia\Cli\Mcp\Writers\TomlConfigWriter;

/**
 * Puts the Unolia MCP server into one agent for one scope: through the agent's own
 * command line when it has one and the binary is here, otherwise by merging into its
 * config file. Running it twice is safe, an existing `unolia` entry is replaced.
 */
final class Installer
{
    public function __construct(
        private readonly AgentCli $cli,
        private readonly ConfigPaths $configPaths,
        private readonly Paths $paths,
    ) {}

    public function plan(Agent $agent, Scope $scope, Platform $platform, string $url, string $cwd): InstallStep
    {
        $command = $agent->cliCommand($scope, $url);
        $binary = $agent->binary();

        if ($command !== null && $binary !== null && $this->cli->has($binary)) {
            return InstallStep::run($agent, $command);
        }

        $file = $agent->configFile($scope, $platform);

        if ($file === null) {
            return InstallStep::skip($agent, $agent->unsupportedReason($scope, $url) ?? 'not supported for this scope');
        }

        $path = $this->configPaths->expand($file, $scope === Scope::Local ? $cwd : null);

        return InstallStep::write($agent, $path, $this->configPaths->display($path, $cwd));
    }

    public function apply(InstallStep $step, string $url): InstallResult
    {
        return match ($step->action) {
            'run' => $this->runCommand($step),
            'write' => $this->writeConfig($step, $url),
            default => InstallResult::skipped($step->reason),
        };
    }

    private function runCommand(InstallStep $step): InstallResult
    {
        $result = $this->cli->execute($step->command);

        if (! $result->ran) {
            return InstallResult::failed($step->commandLabel().' could not be started');
        }

        // `claude mcp add` exits non zero when the server is already registered. The
        // connector is in place either way.
        if (! $result->successful() && ! str_contains($result->error, 'already exists')) {
            $detail = trim($result->error) !== '' ? trim($result->error) : 'exit code '.$result->exitCode;

            return InstallResult::failed($step->commandLabel().' failed: '.$detail);
        }

        return InstallResult::installed('via '.$step->commandLabel());
    }

    private function writeConfig(InstallStep $step, string $url): InstallResult
    {
        $agent = $step->agent;
        $existing = is_file($step->path) ? (string) @file_get_contents($step->path) : '';

        $contents = str_ends_with($step->path, '.toml')
            ? TomlConfigWriter::merge($existing, $agent->configKey(), Agent::SERVER_KEY, $agent->serverConfig($url))
            : JsonConfigWriter::merge($existing, $agent->newFileBase(), $agent->configKey(), Agent::SERVER_KEY, $agent->serverConfig($url));

        if ($contents === null) {
            return InstallResult::failed(
                $step->displayPath.' could not be updated safely (comments or invalid JSON). Add the server by hand with unolia mcp setup --print',
            );
        }

        try {
            $this->paths->writeAtomic($step->path, $contents, 0644);
        } catch (CliError $error) {
            return InstallResult::failed($error->getMessage());
        }

        return InstallResult::installed($step->displayPath);
    }
}
