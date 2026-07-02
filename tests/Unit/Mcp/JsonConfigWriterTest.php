<?php

use Illuminate\Support\Facades\File;
use Unolia\UnoliaCLI\Mcp\Writers\JsonConfigWriter;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/unolia-cli-test-'.uniqid();
    File::makeDirectory($this->dir, recursive: true);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('creates the file and parent directories', function () {
    $path = $this->dir.'/nested/deep/mcp.json';

    $result = (new JsonConfigWriter($path))
        ->write('mcpServers', 'unolia', ['type' => 'http', 'url' => 'https://unolia.test/mcp/team']);

    expect($result)->toBeTrue();
    expect(json_decode((string) File::get($path), true))->toBe([
        'mcpServers' => [
            'unolia' => ['type' => 'http', 'url' => 'https://unolia.test/mcp/team'],
        ],
    ]);
});

it('merges into an existing config without touching siblings', function () {
    $path = $this->dir.'/mcp.json';
    File::put($path, json_encode([
        'theme' => 'dark',
        'mcpServers' => [
            'other' => ['command' => 'npx', 'args' => ['-y', 'other-server']],
        ],
    ]));

    $result = (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['type' => 'http', 'url' => 'https://example.com/mcp']);

    expect($result)->toBeTrue();
    expect(json_decode((string) File::get($path), true))->toBe([
        'theme' => 'dark',
        'mcpServers' => [
            'other' => ['command' => 'npx', 'args' => ['-y', 'other-server']],
            'unolia' => ['type' => 'http', 'url' => 'https://example.com/mcp'],
        ],
    ]);
});

it('replaces an existing unolia entry', function () {
    $path = $this->dir.'/mcp.json';
    File::put($path, json_encode([
        'mcpServers' => ['unolia' => ['type' => 'http', 'url' => 'https://old.example.com/mcp']],
    ]));

    (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['type' => 'http', 'url' => 'https://new.example.com/mcp']);

    expect(json_decode((string) File::get($path), true)['mcpServers']['unolia']['url'])
        ->toBe('https://new.example.com/mcp');
});

it('treats a dotted config key as literal, not nesting', function () {
    $path = $this->dir.'/settings.json';

    (new JsonConfigWriter($path))->write('amp.mcpServers', 'unolia', ['url' => 'https://example.com/mcp']);

    $config = json_decode((string) File::get($path), true);
    expect($config)->toHaveKey('amp.mcpServers');
    expect($config)->not->toHaveKey('amp');
});

it('seeds the new file base only when creating the file', function () {
    $path = $this->dir.'/opencode.json';
    $base = ['$schema' => 'https://opencode.ai/config.json'];

    (new JsonConfigWriter($path, $base))->write('mcp', 'unolia', ['type' => 'remote', 'url' => 'https://example.com/mcp']);
    expect(json_decode((string) File::get($path), true))->toHaveKey('$schema');

    $existing = $this->dir.'/existing.json';
    File::put($existing, json_encode(['mcp' => []]));
    (new JsonConfigWriter($existing, $base))->write('mcp', 'unolia', ['type' => 'remote', 'url' => 'https://example.com/mcp']);
    expect(json_decode((string) File::get($existing), true))->not->toHaveKey('$schema');
});

it('keeps empty objects as objects when re-encoding', function () {
    $path = $this->dir.'/opencode.json';

    (new JsonConfigWriter($path))->write('mcp', 'unolia', [
        'type' => 'remote',
        'enabled' => true,
        'url' => 'https://example.com/mcp',
        'oauth' => new stdClass,
    ]);

    expect((string) File::get($path))->toContain('"oauth": {}');
});

it('preserves sibling empty objects in the existing config', function () {
    $path = $this->dir.'/mcp.json';
    File::put($path, '{"mcpServers": {"other": {"env": {}}}}');

    (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['url' => 'https://example.com/mcp']);

    expect((string) File::get($path))->toContain('"env": {}');
});

it('refuses to touch a file with JSONC comments', function () {
    $path = $this->dir.'/mcp.json';
    $original = "{\n    // my servers\n    \"servers\": {}\n}\n";
    File::put($path, $original);

    $result = (new JsonConfigWriter($path))->write('servers', 'unolia', ['url' => 'https://example.com/mcp']);

    expect($result)->toBeFalse();
    expect((string) File::get($path))->toBe($original);
});

it('refuses to touch a file with broken JSON', function () {
    $path = $this->dir.'/mcp.json';
    File::put($path, '{"broken":');

    $result = (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['url' => 'https://example.com/mcp']);

    expect($result)->toBeFalse();
    expect((string) File::get($path))->toBe('{"broken":');
});

it('refuses when the config key holds a non-object', function () {
    $path = $this->dir.'/mcp.json';
    $original = json_encode(['mcpServers' => 'not-an-object']);
    File::put($path, $original);

    $result = (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['url' => 'https://example.com/mcp']);

    expect($result)->toBeFalse();
    expect((string) File::get($path))->toBe($original);
});

it('treats an empty or {} file as a fresh file', function () {
    $path = $this->dir.'/mcp.json';
    File::put($path, "{}\n");

    $result = (new JsonConfigWriter($path))->write('mcpServers', 'unolia', ['url' => 'https://example.com/mcp']);

    expect($result)->toBeTrue();
    expect(json_decode((string) File::get($path), true))->toHaveKey('mcpServers');
});
