<?php

declare(strict_types=1);

use Tests\Support\FakeAgentCli;
use Unolia\Cli\Local\ProcessResult;
use Unolia\Cli\Mcp\AgentCli;

const MCP_URL = 'https://unolia.example/mcp/team';

it('writes the connector into the project config of file based agents', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--local', '--agent', 'cursor,kiro', '--agent', 'vscode', '--url', MCP_URL, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.cursor/mcp.json'))->toBe(['mcpServers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]]])
        ->and($cli->home->readJson('.kiro/settings/mcp.json'))->toBe(['mcpServers' => ['unolia' => ['url' => MCP_URL]]])
        ->and($cli->home->readJson('.vscode/mcp.json'))->toBe(['servers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]]])
        ->and($result->stdout)->toContain('.cursor/mcp.json')
        ->and($result->stdout)->toContain('sign in to Unolia');
});

it('writes Codex config as TOML', function () {
    $cli = cli();

    $cli->run('mcp', 'setup', '--local', '--agent', 'codex', '--url', MCP_URL, '--yes');

    expect($cli->home->read('.codex/config.toml'))->toBe("[mcp_servers.unolia]\nurl = \"".MCP_URL."\"\n");
});

it('writes user level configs under --global, honoring XDG_CONFIG_HOME', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'cursor,opencode,amp,gemini,junie', '--url', MCP_URL, '--yes');

    $read = static fn (string $relative): array => (array) json_decode((string) file_get_contents($cli->home->homePath($relative)), true);

    expect($result->exitCode)->toBe(0)
        ->and($read('.cursor/mcp.json')['mcpServers']['unolia'])->toBe(['type' => 'http', 'url' => MCP_URL])
        ->and($read('.config/opencode/opencode.json')['$schema'])->toBe('https://opencode.ai/config.json')
        ->and($read('.config/opencode/opencode.json')['mcp']['unolia'])->toBe(['type' => 'remote', 'enabled' => true, 'url' => MCP_URL, 'oauth' => []])
        ->and($read('.config/amp/settings.json')['amp.mcpServers']['unolia'])->toBe(['url' => MCP_URL])
        ->and($read('.gemini/settings.json')['mcpServers']['unolia'])->toBe(['httpUrl' => MCP_URL, 'oauth' => ['enabled' => true]])
        ->and($read('.junie/mcp/mcp.json')['mcpServers']['unolia'])->toBe(['command' => 'npx', 'args' => ['-y', 'mcp-remote', MCP_URL]])
        ->and($result->stdout)->toContain('~/.cursor/mcp.json');
});

it('registers the server through the Claude Code CLI when it is installed', function () {
    $agents = new FakeAgentCli(['claude']);
    $cli = cli()->withService(AgentCli::class, $agents);

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'claude', '--url', MCP_URL, '--yes', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($agents->ran)->toBe([['claude', 'mcp', 'add', '--transport', 'http', 'unolia', MCP_URL, '--scope', 'user']])
        ->and($result->json()['results'][0])->toMatchArray(['agent' => 'claude', 'status' => 'installed', 'detail' => 'via claude mcp add']);
});

it('treats an already registered Claude Code server as installed', function () {
    $agents = new FakeAgentCli(['claude'], new ProcessResult(true, 1, '', 'MCP server "unolia" already exists'));
    $cli = cli()->withService(AgentCli::class, $agents);

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'claude', '--url', MCP_URL, '--yes', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['results'][0]['status'])->toBe('installed');
});

it('exits 1 when the agent CLI refuses', function () {
    $agents = new FakeAgentCli(['claude'], new ProcessResult(true, 1, '', 'boom'));
    $cli = cli()->withService(AgentCli::class, $agents);

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'claude', '--url', MCP_URL, '--yes', '--json');

    expect($result->exitCode)->toBe(1)
        ->and($result->json()['results'][0])->toMatchArray(['status' => 'failed', 'detail' => 'claude mcp add failed: boom']);
});

it('skips a global Claude Code install when the claude binary is missing', function () {
    $result = cli()->run('mcp', 'setup', '--global', '--agent', 'claude', '--url', MCP_URL, '--yes', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['results'][0]['status'])->toBe('skipped')
        ->and($result->json()['results'][0]['detail'])->toContain('claude binary was not found')
        ->and($result->json()['notes'])->toBe([]);
});

it('registers the server through the VS Code CLI when it is installed', function () {
    $agents = new FakeAgentCli(['code']);
    $cli = cli()->withService(AgentCli::class, $agents);

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'vscode', '--url', MCP_URL, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($agents->ran)->toBe([['code', '--add-mcp', '{"name":"unolia","type":"http","url":"'.MCP_URL.'"}']]);
});

it('falls back to the VS Code user config file when the code binary is missing', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--global', '--agent', 'vscode', '--url', MCP_URL, '--yes', '--json');

    $path = (string) $result->json()['results'][0]['detail'];

    expect($path)->toStartWith('~/')
        ->and($path)->toEndWith('mcp.json')
        ->and(file_get_contents($cli->home->homePath(substr($path, 2))))->toContain(MCP_URL);
});

it('replaces the unolia entry on a second run instead of duplicating it', function () {
    $cli = cli();

    $cli->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', 'https://old.example/mcp', '--yes');
    $cli->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', MCP_URL, '--yes');

    expect($cli->home->readJson('.cursor/mcp.json')['mcpServers'] ?? null)->toBe(['unolia' => ['type' => 'http', 'url' => MCP_URL]]);
});

it('leaves a config file it cannot parse alone and exits 1', function () {
    $cli = cli();
    $original = "{\n    // my servers\n    \"mcpServers\": {}\n}";
    $cli->home->write('.cursor/mcp.json', $original);

    $result = $cli->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', MCP_URL, '--yes');

    expect($result->exitCode)->toBe(1)
        ->and($result->stdout)->toContain('could not be updated safely')
        ->and($cli->home->read('.cursor/mcp.json'))->toBe($original);
});

it('prints the snippet with --print and touches nothing', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--print', '--url', MCP_URL);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('"mcpServers"')
        ->and($result->stdout)->toContain(MCP_URL)
        ->and(is_dir($cli->home->path('.cursor')))->toBeFalse();
});

it('includes the snippet when manual is one of the agents', function () {
    $result = cli()->run('mcp', 'setup', '--local', '--agent', 'cursor,manual', '--url', MCP_URL, '--yes', '--json');

    expect($result->json()['snippet'])->toBe(['mcpServers' => ['unolia' => ['type' => 'http', 'url' => MCP_URL]]]);
});

it('builds the default URL from the configured host', function () {
    $result = cli()->env(['UNOLIA_HOST' => 'unolia.example'])->run('mcp', 'setup', '--print');

    expect($result->stdout)->toContain('https://unolia.example/mcp/team');
});

it('lets UNOLIA_MCP_URL override the default URL', function () {
    $result = cli()->env(['UNOLIA_MCP_URL' => 'https://mcp.example/team'])->run('mcp', 'setup', '--print');

    expect($result->stdout)->toContain('https://mcp.example/team');
});

it('rejects an invalid URL', function () {
    $result = cli()->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', 'not-a-url', '--yes');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('invalid MCP server URL');
});

it('rejects an unknown agent and lists the known ones', function () {
    $result = cli()->run('mcp', 'setup', '--local', '--agent', 'cursor,nope', '--url', MCP_URL, '--yes', '--json');

    expect($result->exitCode)->toBe(2)
        ->and($result->errorJson()['error']['message'])->toBe('unknown agent nope')
        ->and($result->errorJson()['error']['details']['candidates'])->toContain('claude', 'manual');
});

it('rejects --global combined with --local', function () {
    $result = cli()->run('mcp', 'setup', '--global', '--local', '--agent', 'cursor', '--yes');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not both');
});

it('needs a scope in a pipe', function () {
    $result = cli()->run('mcp', 'setup', '--agent', 'cursor', '--yes', '--json');

    expect($result->exitCode)->toBe(2)
        ->and($result->errorJson()['error']['code'])->toBe('missing_input')
        ->and($result->errorJson()['error']['details']['flag'])->toBe('--global or --local');
});

it('needs an agent in a pipe and lists the candidates', function () {
    $result = cli()->run('mcp', 'setup', '--local', '--yes', '--json');

    expect($result->exitCode)->toBe(2)
        ->and($result->errorJson()['error']['details']['flag'])->toBe('--agent')
        ->and($result->errorJson()['error']['details']['candidates'])->toContain('Claude Code');
});

it('needs a confirmation in a pipe and writes nothing without --yes', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', MCP_URL);

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation')
        ->and($cli->home->read('.cursor/mcp.json'))->toBeNull();
});

it('shows the plan under --dry-run and writes nothing', function () {
    $cli = cli();

    $result = $cli->run('mcp', 'setup', '--local', '--agent', 'cursor,claude', '--url', MCP_URL, '--dry-run', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['dry_run'])->toBeTrue()
        ->and($result->json()['plan'][0])->toMatchArray(['agent' => 'cursor', 'action' => 'write', 'path' => $cli->home->path('.cursor/mcp.json')])
        ->and($result->json()['plan'][1])->toMatchArray(['agent' => 'claude', 'action' => 'write', 'path' => $cli->home->path('.mcp.json')])
        ->and($cli->home->read('.cursor/mcp.json'))->toBeNull();
});

it('asks for the scope and the agents at a terminal', function () {
    $cli = cli()->answers([
        'Where should the Unolia connector be installed?' => 'local',
        'Which AI agents should be configured?' => ['cursor'],
        'Configure 1 agent?' => true,
    ]);

    $result = $cli->run('mcp', 'setup', '--url', MCP_URL);

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.cursor/mcp.json'))->not->toBeNull();
});

it('does nothing when the confirmation is declined at a terminal', function () {
    $cli = cli()->answers(['Configure 1 agent?' => false]);

    $result = $cli->run('mcp', 'setup', '--local', '--agent', 'cursor', '--url', MCP_URL);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Nothing was written.')
        ->and($cli->home->read('.cursor/mcp.json'))->toBeNull();
});

it('still answers to the old unolia mcp spelling', function () {
    $result = cli()->run('mcp', '--print', '--url', MCP_URL);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain(MCP_URL);
});
