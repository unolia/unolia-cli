<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Unolia\UnoliaCLI\Mcp\Agent;
use Unolia\UnoliaCLI\Mcp\Paths;
use Unolia\UnoliaCLI\Mcp\Platform;
use Unolia\UnoliaCLI\Mcp\Scope;

const MCP_URL = 'https://unolia.test/mcp/team';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/unolia-cli-test-'.uniqid();
    $this->home = $this->dir.'/home';
    $this->cwd = $this->dir.'/project';
    File::makeDirectory($this->home, recursive: true);
    File::makeDirectory($this->cwd, recursive: true);

    $this->originalHome = $_SERVER['HOME'] ?? null;
    $this->originalXdg = $_SERVER['XDG_CONFIG_HOME'] ?? null;
    $this->originalCwd = (string) getcwd();
    $_SERVER['HOME'] = $this->home;
    $_SERVER['XDG_CONFIG_HOME'] = $this->home.'/.config';
    chdir($this->cwd);
});

afterEach(function () {
    $_SERVER['HOME'] = $this->originalHome;
    $_SERVER['XDG_CONFIG_HOME'] = $this->originalXdg;
    chdir($this->originalCwd);
    File::deleteDirectory($this->dir);
});

it('installs the connector locally for file-based agents', function () {
    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor,kiro,vscode', '--url' => MCP_URL])
        ->assertSuccessful();

    expect(json_decode((string) File::get($this->cwd.'/.cursor/mcp.json'), true))->toBe([
        'mcpServers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]],
    ]);
    expect(json_decode((string) File::get($this->cwd.'/.kiro/settings/mcp.json'), true))->toBe([
        'mcpServers' => ['unolia' => ['url' => MCP_URL]],
    ]);
    expect(json_decode((string) File::get($this->cwd.'/.vscode/mcp.json'), true))->toBe([
        'servers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]],
    ]);
});

it('installs the connector locally for Codex as TOML', function () {
    $this->artisan('mcp', ['--local' => true, '--agents' => 'codex', '--url' => MCP_URL])
        ->assertSuccessful();

    expect((string) File::get($this->cwd.'/.codex/config.toml'))
        ->toBe("[mcp_servers.unolia]\nurl = \"".MCP_URL."\"\n");
});

it('installs the connector globally into user-level configs', function () {
    $this->artisan('mcp', ['--global' => true, '--agents' => 'cursor,opencode,amp,gemini,junie', '--url' => MCP_URL])
        ->assertSuccessful();

    expect(json_decode((string) File::get($this->home.'/.cursor/mcp.json'), true)['mcpServers']['unolia'])
        ->toBe(['type' => 'http', 'url' => MCP_URL]);

    $opencode = json_decode((string) File::get($this->home.'/.config/opencode/opencode.json'), true);
    expect($opencode['$schema'])->toBe('https://opencode.ai/config.json');
    expect($opencode['mcp']['unolia'])->toBe([
        'type' => 'remote', 'enabled' => true, 'url' => MCP_URL, 'oauth' => [],
    ]);

    expect(json_decode((string) File::get($this->home.'/.config/amp/settings.json'), true)['amp.mcpServers']['unolia'])
        ->toBe(['url' => MCP_URL]);

    expect(json_decode((string) File::get($this->home.'/.gemini/settings.json'), true)['mcpServers']['unolia'])
        ->toBe(['httpUrl' => MCP_URL, 'oauth' => ['enabled' => true]]);

    expect(json_decode((string) File::get($this->home.'/.junie/mcp/mcp.json'), true)['mcpServers']['unolia'])
        ->toBe(['command' => 'npx', 'args' => ['-y', 'mcp-remote', MCP_URL]]);
});

it('adds the connector to Claude Code through its CLI when available', function () {
    Process::fake();

    $this->artisan('mcp', ['--global' => true, '--agents' => 'claude', '--url' => MCP_URL])
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'claude', 'mcp', 'add', '--transport', 'http', 'unolia', MCP_URL, '--scope', 'user',
    ]);
});

it('treats an already-registered Claude Code server as installed', function () {
    Process::fake(function (PendingProcess $process) {
        if (is_array($process->command)) {
            return Process::result(errorOutput: 'MCP server "unolia" already exists', exitCode: 1);
        }

        return Process::result();
    });

    $this->artisan('mcp', ['--global' => true, '--agents' => 'claude', '--url' => MCP_URL])
        ->assertSuccessful();
});

it('skips Claude Code global install when the claude binary is missing', function () {
    Process::fake(fn () => Process::result(exitCode: 1));

    $this->artisan('mcp', ['--global' => true, '--agents' => 'claude', '--url' => MCP_URL])
        ->expectsOutputToContain('binary was not found')
        ->assertSuccessful();
});

it('adds the connector to VS Code through its CLI when available', function () {
    Process::fake();

    $this->artisan('mcp', ['--global' => true, '--agents' => 'vscode', '--url' => MCP_URL])
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'code', '--add-mcp', '{"name":"unolia","type":"http","url":"'.MCP_URL.'"}',
    ]);
});

it('falls back to the VS Code user config file when the code binary is missing', function () {
    Process::fake(fn () => Process::result(exitCode: 1));

    $this->artisan('mcp', ['--global' => true, '--agents' => 'vscode', '--url' => MCP_URL])
        ->assertSuccessful();

    $path = Paths::expand((string) Agent::VSCode->configFile(Scope::Global, Platform::current()));

    expect(json_decode((string) File::get($path), true))->toBe([
        'servers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]],
    ]);
});

it('replaces the unolia entry on re-runs instead of duplicating', function () {
    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor', '--url' => 'https://old.example.com/mcp'])->assertSuccessful();
    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor', '--url' => MCP_URL])->assertSuccessful();

    expect(json_decode((string) File::get($this->cwd.'/.cursor/mcp.json'), true)['mcpServers'])
        ->toBe(['unolia' => ['type' => 'http', 'url' => MCP_URL]]);
});

it('fails without touching a config file it cannot parse', function () {
    File::makeDirectory($this->cwd.'/.cursor');
    $original = "{\n    // my servers\n    \"mcpServers\": {}\n}";
    File::put($this->cwd.'/.cursor/mcp.json', $original);

    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor', '--url' => MCP_URL])
        ->assertFailed();

    expect((string) File::get($this->cwd.'/.cursor/mcp.json'))->toBe($original);
});

it('prints the manual snippet with --print', function () {
    $this->artisan('mcp', ['--print' => true, '--url' => MCP_URL])
        ->expectsOutputToContain('"url": "'.MCP_URL.'"')
        ->assertSuccessful();
});

it('uses the configured URL when --url is not passed', function () {
    config(['settings.mcp.url' => 'https://configured.example.com/mcp/team']);

    $this->artisan('mcp', ['--print' => true])
        ->expectsOutputToContain('https://configured.example.com/mcp/team')
        ->assertSuccessful();
});

it('rejects an unknown action', function () {
    $this->artisan('mcp', ['action' => 'teardown'])
        ->expectsOutputToContain('Unknown action')
        ->assertFailed();
});

it('rejects an unknown agent key', function () {
    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor,nope', '--url' => MCP_URL])
        ->expectsOutputToContain('Unknown agent "nope"')
        ->assertFailed();
});

it('rejects --global combined with --local', function () {
    $this->artisan('mcp', ['--global' => true, '--local' => true, '--agents' => 'cursor'])
        ->expectsOutputToContain('not both')
        ->assertFailed();
});

it('rejects an invalid MCP URL', function () {
    $this->artisan('mcp', ['--local' => true, '--agents' => 'cursor', '--url' => 'not-a-url'])
        ->expectsOutputToContain('Invalid MCP server URL')
        ->assertFailed();
});

it('requires a scope when running non-interactively', function () {
    $this->artisan('mcp', ['--agents' => 'cursor', '--no-interaction' => true])
        ->expectsOutputToContain('--global or --local')
        ->assertFailed();
});

it('asks for scope and agents interactively', function () {
    Process::fake(fn () => Process::result(exitCode: 1));

    $this->artisan('mcp')
        ->expectsQuestion('Where should the Unolia connector be installed?', Scope::Local->value)
        ->expectsQuestion('Which AI agents would you like to configure?', ['cursor'])
        ->assertSuccessful();

    expect(File::exists($this->cwd.'/.cursor/mcp.json'))->toBeTrue();
});
