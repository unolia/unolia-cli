<?php

declare(strict_types=1);

use Unolia\Cli\Mcp\Writers\TomlConfigWriter;

it('writes a single table into an empty document', function () {
    expect(TomlConfigWriter::merge('', 'mcp_servers', 'unolia', ['url' => 'https://unolia.example/mcp/team']))
        ->toBe("[mcp_servers.unolia]\nurl = \"https://unolia.example/mcp/team\"\n");
});

it('appends after existing content without touching it', function () {
    $existing = "# codex config\nmodel = \"o3\"\n\n[mcp_servers.other]\ncommand = \"npx\"\n";

    $merged = TomlConfigWriter::merge($existing, 'mcp_servers', 'unolia', ['url' => 'https://unolia.example/mcp/team']);

    expect($merged)->toContain("# codex config\nmodel = \"o3\"")
        ->and($merged)->toContain("[mcp_servers.other]\ncommand = \"npx\"")
        ->and($merged)->toEndWith("[mcp_servers.unolia]\nurl = \"https://unolia.example/mcp/team\"\n");
});

it('replaces an existing unolia block and keeps what follows it', function () {
    $existing = implode("\n", ['[mcp_servers.unolia]', 'url = "https://old.example/mcp"', '', '[model_providers.custom]', 'name = "Custom"', '']);

    $merged = TomlConfigWriter::merge($existing, 'mcp_servers', 'unolia', ['url' => 'https://unolia.example/mcp/team']);

    expect($merged)->not->toContain('old.example')
        ->and(substr_count($merged, '[mcp_servers.unolia]'))->toBe(1)
        ->and($merged)->toContain("[model_providers.custom]\nname = \"Custom\"")
        ->and($merged)->toEndWith("[mcp_servers.unolia]\nurl = \"https://unolia.example/mcp/team\"\n");
});

it('formats booleans, numbers and string arrays', function () {
    $merged = TomlConfigWriter::merge('', 'mcp_servers', 'unolia', [
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://unolia.example/mcp/team'],
        'enabled' => true,
        'timeout' => 30,
    ]);

    expect($merged)->toBe(implode("\n", [
        '[mcp_servers.unolia]',
        'command = "npx"',
        'args = ["-y", "mcp-remote", "https://unolia.example/mcp/team"]',
        'enabled = true',
        'timeout = 30',
        '',
    ]));
});
