<?php

declare(strict_types=1);

use Tests\Support\FakeDns;
use Unolia\Cli\Support\Dns;

function answers(): array
{
    return [
        ['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.10'],
        ['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.11'],
    ];
}

it('prints the answers of the resolver', function () {
    $dns = new FakeDns(answers());

    $result = cli()->withService(Dns::class, $dns)->run('domain', 'dig', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('203.0.113.10')
        ->and($result->stdout)->toContain('203.0.113.11')
        ->and($dns->queries)->toBe([['domain' => 'acme.com', 'type' => 'A', 'server' => '1.1.1.1']]);
});

it('asks the resolver named by --server for the type given, and is reachable as dig', function () {
    $dns = new FakeDns(answers());

    $result = cli()->withService(Dns::class, $dns)->run('dig', 'acme.com', 'txt', '--server', '8.8.8.8', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toHaveCount(2)
        ->and($dns->queries[0]['type'])->toBe('TXT')
        ->and($dns->queries[0]['server'])->toBe('8.8.8.8');
});

it('exits 4 when there is no record', function () {
    $result = cli()->withService(Dns::class, new FakeDns([]))->run('domain', 'dig', 'acme.com', 'AAAA');

    expect($result->exitCode)->toBe(4)
        ->and($result->stdout)->toContain('No AAAA record for acme.com');
});

it('exits 1 when the resolver does not answer', function () {
    $result = cli()->withService(Dns::class, new FakeDns([], failure: 'timed out'))->run('domain', 'dig', 'acme.com');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->toContain('did not answer');
});

it('refuses a record type it does not know', function () {
    $result = cli()->withService(Dns::class, new FakeDns([]))->run('domain', 'dig', 'acme.com', 'LOC');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not a record type');
});

it('exits 2 without a domain in a pipe', function () {
    $result = cli()->withService(Dns::class, new FakeDns([]))->run('domain', 'dig');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing <domain>');
});
