<?php

namespace Unolia\UnoliaCLI\Mcp;

use stdClass;

/**
 * Every AI agent the `mcp setup` command can configure, with all its
 * per-agent data (detection, config file locations, payload shape) as
 * match() methods. The backing values are the keys accepted by --agents=.
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
     * How to tell the agent is installed on this machine: a shell `command`
     * that must succeed and/or `paths` (dirs, `~`/`%VAR%`/glob aware).
     *
     * @return array{command?: string, paths?: list<string>}
     */
    public function systemDetection(Platform $platform): array
    {
        $windows = $platform === Platform::Windows;

        return match ($this) {
            self::Claude => ['command' => $windows ? 'where claude' : 'command -v claude'],
            self::Cursor => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/Cursor.app']],
                Platform::Linux => ['paths' => ['/opt/cursor', '/usr/local/bin/cursor', '~/.local/bin/cursor']],
                Platform::Windows => ['paths' => ['%ProgramFiles%\Cursor', '%LOCALAPPDATA%\Programs\Cursor']],
            },
            self::VSCode => match ($platform) {
                Platform::Darwin => ['paths' => ['/Applications/Visual Studio Code.app']],
                Platform::Linux => ['command' => 'command -v code'],
                Platform::Windows => ['paths' => ['%ProgramFiles%\Microsoft VS Code', '%LOCALAPPDATA%\Programs\Microsoft VS Code']],
            },
            self::Codex => ['command' => $windows ? 'where codex' : 'command -v codex'],
            self::Gemini => ['command' => $windows ? 'where gemini' : 'command -v gemini'],
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
            self::OpenCode => ['command' => $windows ? 'where opencode' : 'command -v opencode'],
            self::Amp => $windows
                ? ['command' => 'where amp', 'paths' => ['%USERPROFILE%\.amp', '%USERPROFILE%\.config\amp']]
                : ['command' => 'command -v amp', 'paths' => ['~/.amp', '~/.config/amp']],
        };
    }

    /**
     * How to tell the agent is used in the current directory: `paths` (dirs)
     * and/or `files`, both relative to the cwd.
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
     * The config file to merge the server into for a scope, or null when the
     * scope is only reachable through a shell command (or not at all).
     * Local paths are cwd-relative; global paths use `~`/`%VAR%`.
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
            // Claude's global config (~/.claude.json) is a large stateful file
            // the CLI owns - only `claude mcp add` may write it.
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

    /**
     * The top-level key the servers live under. A literal key, even when it
     * contains a dot (Amp's "amp.mcpServers").
     */
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
     * The server entry written under configKey(). Shapes differ because
     * clients disagree on how a remote HTTP MCP server is declared.
     *
     * @return array<string, mixed>
     */
    public function serverConfig(string $url): array
    {
        return match ($this) {
            self::Claude, self::Cursor, self::VSCode => ['type' => 'http', 'url' => $url],
            self::Codex => ['url' => $url],
            self::Gemini => ['httpUrl' => $url, 'oauth' => ['enabled' => true]],
            // Junie's mcp.json is stdio-format only - bridge through mcp-remote.
            self::Junie => ['command' => 'npx', 'args' => ['-y', 'mcp-remote', $url]],
            self::Kiro, self::Amp => ['url' => $url],
            self::OpenCode => ['type' => 'remote', 'enabled' => true, 'url' => $url, 'oauth' => new stdClass],
        };
    }

    /**
     * Top-level keys to seed when the config file does not exist yet.
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

    /** The binary a shellCommand() needs on the PATH. */
    public function binary(): ?string
    {
        return match ($this) {
            self::Claude => 'claude',
            self::VSCode => 'code',
            default => null,
        };
    }

    /**
     * The agent's own CLI invocation for installing the server, preferred
     * over writing the config file when the binary is available.
     *
     * @return list<string>|null
     */
    public function shellCommand(Scope $scope, string $url): ?array
    {
        return match ($this) {
            self::Claude => $scope === Scope::Global
                ? ['claude', 'mcp', 'add', '--transport', 'http', self::SERVER_KEY, $url, '--scope', 'user']
                : null,
            self::VSCode => $scope === Scope::Global
                ? ['code', '--add-mcp', (string) json_encode(['name' => self::SERVER_KEY, 'type' => 'http', 'url' => $url], JSON_UNESCAPED_SLASHES)]
                : null,
            default => null,
        };
    }

    /**
     * Post-install hints for the summary.
     *
     * @return list<string>
     */
    public function notes(Scope $scope): array
    {
        return match ($this) {
            self::Codex => array_values(array_filter([
                'Codex: run `codex mcp login '.self::SERVER_KEY.'` to complete the sign-in.',
                $scope === Scope::Local ? 'Codex only reads project config in trusted projects.' : null,
            ])),
            self::Junie => ['Junie: the connector runs through `npx mcp-remote`, which requires Node.js.'],
            default => [],
        };
    }

    /** Why a scope had to be skipped (shown as the `–` detail). */
    public function unsupportedReason(Scope $scope): ?string
    {
        return match ($this) {
            self::Claude => $scope === Scope::Global
                ? 'the `claude` binary was not found - install Claude Code or run `claude mcp add --transport http '.self::SERVER_KEY.' <url> --scope user` yourself'
                : null,
            default => null,
        };
    }
}
