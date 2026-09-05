<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

use stdClass;

/**
 * Every AI agent `mcp setup` can configure, with its detection rules, config file
 * locations and payload shape as match() methods. The backing values are the keys
 * accepted by --agent.
 */
enum Agent: string
{
    /** The server id written into every client config. */
    public const SERVER_KEY = 'unolia';

    case Claude = 'claude';
    case Cursor = 'cursor';
    case VSCode = 'vscode';
    case Codex = 'codex';
    case Gemini = 'gemini';
    case Junie = 'junie';
    case Kiro = 'kiro';
    case OpenCode = 'opencode';
    case Amp = 'amp';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Claude => 'Claude Code',
            self::Cursor => 'Cursor',
            self::VSCode => 'VS Code (Copilot)',
            self::Codex => 'Codex',
            self::Gemini => 'Gemini CLI',
            self::Junie => 'Junie (JetBrains)',
            self::Kiro => 'Kiro',
            self::OpenCode => 'OpenCode',
            self::Amp => 'Amp',
        };
    }

    /**
     * How to tell the agent is installed on this machine: a `binary` on the PATH
     * and/or directory `paths` (`~`, `%VAR%` and glob aware).
     *
     * @return array{binary?: string, paths?: list<string>}
     */
    public function systemDetection(Platform $platform): array
    {
        return match ($this) {
            self::Claude => ['binary' => 'claude'],
            self::Cursor => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/Cursor.app']],
                Platform::Linux => ['paths' => ['/opt/cursor', '/usr/local/bin/cursor', '~/.local/bin/cursor']],
                Platform::Windows => ['paths' => ['%ProgramFiles%\Cursor', '%LOCALAPPDATA%\Programs\Cursor']],
            },
            self::VSCode => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/Visual Studio Code.app']],
                Platform::Linux => ['binary' => 'code'],
                Platform::Windows => ['paths' => ['%ProgramFiles%\Microsoft VS Code', '%LOCALAPPDATA%\Programs\Microsoft VS Code']],
            },
            self::Codex => ['binary' => 'codex'],
            self::Gemini => ['binary' => 'gemini'],
            self::Junie => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/PhpStorm.app', '~/Applications/PhpStorm.app']],
                Platform::Linux => ['paths' => ['/opt/phpstorm', '/opt/PhpStorm*', '~/.local/share/JetBrains/Toolbox/apps/PhpStorm/ch-*']],
                Platform::Windows => ['paths' => ['%LOCALAPPDATA%\Programs\PhpStorm', '%LOCALAPPDATA%\JetBrains\Toolbox\apps\PhpStorm*']],
            },
            self::Kiro => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/Kiro.app']],
                Platform::Linux => ['paths' => ['/opt/kiro', '/usr/local/bin/kiro']],
                Platform::Windows => ['paths' => ['%LOCALAPPDATA%\Programs\Kiro']],
            },
            self::OpenCode => ['binary' => 'opencode'],
            self::Amp => $platform === Platform::Windows
                ? ['binary' => 'amp', 'paths' => ['%USERPROFILE%\.amp', '%USERPROFILE%\.config\amp']]
                : ['binary' => 'amp', 'paths' => ['~/.amp', '~/.config/amp']],
        };
    }

    /**
     * How to tell the agent is used in the current directory: `paths` (directories)
     * and/or `files`, both relative to it.
     *
     * @return array{paths?: list<string>, files?: list<string>}
     */
    public function projectDetection(): array
    {
        return match ($this) {
            self::Claude => ['paths' => ['.claude'], 'files' => ['CLAUDE.md']],
            self::Cursor => ['paths' => ['.cursor']],
            self::VSCode => ['paths' => ['.vscode'], 'files' => ['.github/copilot-instructions.md']],
            self::Codex => ['paths' => ['.codex'], 'files' => ['AGENTS.md']],
            self::Gemini => ['paths' => ['.gemini'], 'files' => ['GEMINI.md']],
            self::Junie => ['paths' => ['.idea', '.junie']],
            self::Kiro => ['paths' => ['.kiro']],
            self::OpenCode => ['files' => ['AGENTS.md', 'opencode.json']],
            self::Amp => ['paths' => ['.amp']],
        };
    }

    /**
     * The config file to merge the server into for a scope, or null when the scope is
     * only reachable through the agent's own command line. Local paths are relative to
     * the working directory, global paths use `~` or `%VAR%`.
     */
    public function configFile(Scope $scope, Platform $platform): ?string
    {
        if ($scope === Scope::Local) {
            return match ($this) {
                self::Claude => '.mcp.json',
                self::Cursor => '.cursor/mcp.json',
                self::VSCode => '.vscode/mcp.json',
                self::Codex => '.codex/config.toml',
                self::Gemini => '.gemini/settings.json',
                self::Junie => '.junie/mcp/mcp.json',
                self::Kiro => '.kiro/settings/mcp.json',
                self::OpenCode => 'opencode.json',
                self::Amp => '.amp/settings.json',
            };
        }

        return match ($this) {
            // ~/.claude.json is a large stateful file Claude Code owns. Only its CLI writes it.
            self::Claude => null,
            self::Cursor => '~/.cursor/mcp.json',
            self::VSCode => match ($platform) {
                Platform::Darwin => '~/Library/Application Support/Code/User/mcp.json',
                Platform::Linux => '~/.config/Code/User/mcp.json',
                Platform::Windows => '%APPDATA%\Code\User\mcp.json',
            },
            self::Codex => '~/.codex/config.toml',
            self::Gemini => '~/.gemini/settings.json',
            self::Junie => '~/.junie/mcp/mcp.json',
            self::Kiro => '~/.kiro/settings/mcp.json',
            self::OpenCode => '~/.config/opencode/opencode.json',
            self::Amp => '~/.config/amp/settings.json',
        };
    }

    /** The top level key the servers live under. Literal, even with a dot in it. */
    public function configKey(): string
    {
        return match ($this) {
            self::VSCode => 'servers',
            self::Codex => 'mcp_servers',
            self::OpenCode => 'mcp',
            self::Amp => 'amp.mcpServers',
            default => 'mcpServers',
        };
    }

    /**
     * The server entry written under configKey(). Clients disagree on how a remote
     * HTTP server is declared.
     *
     * @return array<string, mixed>
     */
    public function serverConfig(string $url): array
    {
        return match ($this) {
            self::Claude, self::Cursor, self::VSCode => ['type' => 'http', 'url' => $url],
            self::Codex, self::Kiro, self::Amp => ['url' => $url],
            self::Gemini => ['httpUrl' => $url, 'oauth' => ['enabled' => true]],
            // Junie only speaks stdio. mcp-remote bridges to the HTTP server.
            self::Junie => ['command' => 'npx', 'args' => ['-y', 'mcp-remote', $url]],
            self::OpenCode => ['type' => 'remote', 'enabled' => true, 'url' => $url, 'oauth' => new stdClass],
        };
    }

    /**
     * Top level keys to seed when the config file does not exist yet.
     *
     * @return array<string, mixed>
     */
    public function newFileBase(): array
    {
        return match ($this) {
            self::OpenCode => ['$schema' => 'https://opencode.ai/config.json'],
            default => [],
        };
    }

    /** The binary a cliCommand() needs on the PATH. */
    public function binary(): ?string
    {
        return match ($this) {
            self::Claude => 'claude',
            self::VSCode => 'code',
            default => null,
        };
    }

    /**
     * The agent's own command for registering the server, preferred over editing its
     * config file when the binary is available.
     *
     * @return list<string>|null
     */
    public function cliCommand(Scope $scope, string $url): ?array
    {
        if ($scope === Scope::Local) {
            return null;
        }

        return match ($this) {
            self::Claude => ['claude', 'mcp', 'add', '--transport', 'http', self::SERVER_KEY, $url, '--scope', 'user'],
            self::VSCode => ['code', '--add-mcp', (string) json_encode(['name' => self::SERVER_KEY, 'type' => 'http', 'url' => $url], JSON_UNESCAPED_SLASHES)],
            default => null,
        };
    }

    /**
     * What to tell the user once the connector is installed.
     *
     * @return list<string>
     */
    public function notes(Scope $scope): array
    {
        return match ($this) {
            self::Codex => array_values(array_filter([
                'Codex: run codex mcp login '.self::SERVER_KEY.' to finish signing in.',
                $scope === Scope::Local ? 'Codex only reads project config in trusted projects.' : null,
            ])),
            self::Junie => ['Junie: the connector runs through npx mcp-remote, which needs Node.js.'],
            default => [],
        };
    }

    /** Why a scope had to be skipped. */
    public function unsupportedReason(Scope $scope, string $url): ?string
    {
        return match ($this) {
            self::Claude => $scope === Scope::Global
                ? 'the claude binary was not found. Install Claude Code, or run: claude mcp add --transport http '.self::SERVER_KEY.' '.$url.' --scope user'
                : null,
            default => null,
        };
    }
}
