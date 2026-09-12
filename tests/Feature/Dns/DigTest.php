<?php

declare(strict_types=1);

use Tests\Support\FakeDns;
use Unolia\Cli\Support\Dns;

function digAnswers(): array
{
    return [
        ['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.10'],
        ['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.11'],
    ];
}

it('prints the answers of the resolver, and is reachable as dig and domain dig', function () {
    $dns = new FakeDns(digAnswers());

    $result = cli()->withoutToken()->withService(Dns::class, $dns)->run('dig', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('203.0.113.10')
        ->and($result->stdout)->toContain('203.0.113.11')
        ->and($result->stdout)->not->toContain('RESOLVER')
        ->and($dns->queries)->toBe([['domain' => 'acme.com', 'type' => 'A', 'server' => '1.1.1.1']]);

    $old = cli()->withoutToken()->withService(Dns::class, new FakeDns(digAnswers()))->run('domain', 'dig', 'acme.com', '--json');

    expect($old->json())->toHaveCount(2);
});

it('asks the resolver named by --server for the type given', function () {
    $dns = new FakeDns(digAnswers());

    $result = cli()->withoutToken()->withService(Dns::class, $dns)->run('dns', 'dig', 'acme.com', 'txt', '--server', '8.8.8.8', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toHaveCount(2)
        ->and($dns->queries[0]['type'])->toBe('TXT')
        ->and($dns->queries[0]['server'])->toBe('8.8.8.8');
});

it('completes a relative name from the project zone', function () {
    $dns = new FakeDns(digAnswers());

    cli()->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()->on('GET', 'v1/domains?project=12', fixture('domains-one.json')))
        ->withService(Dns::class, $dns)
        ->run('dns', 'dig', 'www');

    expect($dns->queries[0]['domain'])->toBe('www.acme.com');
});

it('asks every resolver with --all and marks the ones that disagree', function () {
    $dns = new FakeDns(digAnswers());

    $result = cli()->withoutToken()->withService(Dns::class, $dns)->run('dig', 'acme.com', '--all');

    expect($result->exitCode)->toBe(0)
        ->and(array_column($dns->queries, 'server'))->toBe(['1.1.1.1', '8.8.8.8', '9.9.9.9'])
        ->and($result->stdout)->toContain('RESOLVER')
        ->and($result->stdout)->toContain('8.8.8.8');
});

it('exits 4 when there is no record', function () {
    $result = cli()->withoutToken()->withService(Dns::class, new FakeDns([]))->run('dig', 'acme.com', 'AAAA');

    expect($result->exitCode)->toBe(4)
        ->and($result->stdout)->toContain('No AAAA record for acme.com');
});

it('exits 1 when the resolver does not answer', function () {
    $result = cli()->withoutToken()->withService(Dns::class, new FakeDns([], failure: 'timed out'))->run('dig', 'acme.com');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->toContain('did not answer');
});

it('refuses a record type it does not know and exits 2 without a name in a pipe', function () {
    $result = cli()->withoutToken()->withService(Dns::class, new FakeDns([]))->run('dig', 'acme.com', 'LOC');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not a record type');

    $missing = cli()->withoutToken()->withService(Dns::class, new FakeDns([]))->run('dig');

    expect($missing->exitCode)->toBe(2)
        ->and($missing->stderr)->toContain('missing <name>');
});

it('checks the records of a zone against a resolver', function () {
    $dns = new FakeDns([['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.10']]);

    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains/acme.com/records', fixture('records.json')))
        ->withService(Dns::class, $dns)
        ->run('dns', 'check', 'acme.com');

    // Every query gets the same A answer back: the A records match, the MX does not.
    expect($result->exitCode)->toBe(1)
        ->and($result->stdout)->toContain('3 records checked')
        ->and($result->stdout)->toContain('Unolia has 10 mail.acme.com')
        ->and(count($dns->queries))->toBe(3);

    $json = cli()
        ->withApi(api()->on('GET', 'v1/domains/acme.com/records', fixture('records.json')))
        ->withService(Dns::class, new FakeDns([['name' => 'acme.com', 'type' => 'A', 'ttl' => 300, 'value' => '203.0.113.10']]))
        ->run('dns', 'check', 'acme.com', '--type', 'A', '--json');

    expect($json->exitCode)->toBe(0)
        ->and(array_column($json->json(), 'result'))->toBe(['match', 'match']);
});
