<?php

declare(strict_types=1);

use Unolia\Cli\Mcp\Writers\JsonConfigWriter;

const JSON_SERVER = ['type' => 'http', 'url' => 'https://unolia.example/mcp/team'];

function decoded(?string $json): array
{
    return (array) json_decode((string) $json, true);
}

it('starts a fresh document from nothing', function () {
    $merged = JsonConfigWriter::merge('', [], 'mcpServers', 'unolia', JSON_SERVER);

    expect(decoded($merged))->toBe(['mcpServers' => ['unolia' => JSON_SERVER]])
        ->and($merged)->toEndWith("\n");
});

it('merges into an existing document without touching siblings', function () {
    $existing = (string) json_encode(['theme' => 'dark', 'mcpServers' => ['other' => ['command' => 'npx', 'args' => ['-y', 'other']]]]);

    $merged = JsonConfigWriter::merge($existing, [], 'mcpServers', 'unolia', JSON_SERVER);

    expect(decoded($merged))->toBe([
        'theme' => 'dark',
        'mcpServers' => ['other' => ['command' => 'npx', 'args' => ['-y', 'other']], 'unolia' => JSON_SERVER],
    ]);
});

it('replaces an existing unolia entry', function () {
    $existing = (string) json_encode(['mcpServers' => ['unolia' => ['type' => 'http', 'url' => 'https://old.example/mcp']]]);

    $merged = JsonConfigWriter::merge($existing, [], 'mcpServers', 'unolia', JSON_SERVER);

    expect(decoded($merged)['mcpServers']['unolia']['url'])->toBe('https://unolia.example/mcp/team');
});

it('treats a dotted config key as literal, not nesting', function () {
    $merged = JsonConfigWriter::merge('', [], 'amp.mcpServers', 'unolia', ['url' => 'https://unolia.example/mcp/team']);

    expect(decoded($merged))->toHaveKey('amp.mcpServers')
        ->and(decoded($merged))->not->toHaveKey('amp');
});

it('seeds the new file base only on a fresh document', function () {
    $base = ['$schema' => 'https://opencode.ai/config.json'];

    expect(decoded(JsonConfigWriter::merge('', $base, 'mcp', 'unolia', JSON_SERVER)))->toHaveKey('$schema')
        ->and(decoded(JsonConfigWriter::merge('{"mcp": {}}', $base, 'mcp', 'unolia', JSON_SERVER)))->not->toHaveKey('$schema');
});

it('keeps empty objects as objects', function () {
    $merged = JsonConfigWriter::merge('{"mcpServers": {"other": {"env": {}}}}', [], 'mcpServers', 'unolia', ['url' => 'x', 'oauth' => new stdClass]);

    expect($merged)->toContain('"env": {}')
        ->and($merged)->toContain('"oauth": {}');
});

it('refuses a document with comments, broken JSON or a non object key', function () {
    expect(JsonConfigWriter::merge("{\n // servers\n \"servers\": {}\n}", [], 'servers', 'unolia', JSON_SERVER))->toBeNull()
        ->and(JsonConfigWriter::merge('{"broken":', [], 'mcpServers', 'unolia', JSON_SERVER))->toBeNull()
        ->and(JsonConfigWriter::merge('{"mcpServers": "nope"}', [], 'mcpServers', 'unolia', JSON_SERVER))->toBeNull();
});

it('treats an empty object document as fresh', function () {
    expect(decoded(JsonConfigWriter::merge("{}\n", [], 'mcpServers', 'unolia', JSON_SERVER)))->toHaveKey('mcpServers');
});
