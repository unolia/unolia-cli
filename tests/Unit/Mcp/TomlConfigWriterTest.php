<?php

use Illuminate\Support\Facades\File;
use Unolia\UnoliaCLI\Mcp\Writers\TomlConfigWriter;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/unolia-cli-test-'.uniqid();
    File::makeDirectory($this->dir, recursive: true);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('creates the file and parent directories with a single table', function () {
    $path = $this->dir.'/.codex/config.toml';

    $result = (new TomlConfigWriter($path))->write('mcp_servers', 'unolia', ['url' => 'https://unolia.test/mcp/team']);

    expect($result)->toBeTrue();
    expect((string) File::get($path))->toBe("[mcp_servers.unolia]\nurl = \"https://unolia.test/mcp/team\"\n");
});

it('appends to an existing file without touching other content', function () {
    $path = $this->dir.'/config.toml';
    File::put($path, "# codex config\nmodel = \"o3\"\n\n[mcp_servers.other]\ncommand = \"npx\"\n");

    (new TomlConfigWriter($path))->write('mcp_servers', 'unolia', ['url' => 'https://example.com/mcp']);

    $contents = (string) File::get($path);
    expect($contents)->toContain("# codex config\nmodel = \"o3\"");
    expect($contents)->toContain("[mcp_servers.other]\ncommand = \"npx\"");
    expect($contents)->toEndWith("[mcp_servers.unolia]\nurl = \"https://example.com/mcp\"\n");
});

it('replaces an existing unolia block, preserving what follows it', function () {
    $path = $this->dir.'/config.toml';
    File::put($path, implode("\n", [
        '[mcp_servers.unolia]',
        'url = "https://old.example.com/mcp"',
        '',
        '[model_providers.custom]',
        'name = "Custom"',
        '',
    ]));

    (new TomlConfigWriter($path))->write('mcp_servers', 'unolia', ['url' => 'https://new.example.com/mcp']);

    $contents = (string) File::get($path);
    expect($contents)->not->toContain('old.example.com');
    expect(substr_count($contents, '[mcp_servers.unolia]'))->toBe(1);
    expect($contents)->toContain("[model_providers.custom]\nname = \"Custom\"");
    expect($contents)->toEndWith("[mcp_servers.unolia]\nurl = \"https://new.example.com/mcp\"\n");
});

it('formats booleans, numbers and string arrays', function () {
    $path = $this->dir.'/config.toml';

    (new TomlConfigWriter($path))->write('mcp_servers', 'unolia', [
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://example.com/mcp'],
        'enabled' => true,
        'timeout' => 30,
    ]);

    expect((string) File::get($path))->toBe(implode("\n", [
        '[mcp_servers.unolia]',
        'command = "npx"',
        'args = ["-y", "mcp-remote", "https://example.com/mcp"]',
        'enabled = true',
        'timeout = 30',
        '',
    ]));
});
