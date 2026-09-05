<?php

declare(strict_types=1);

it('adds a record', function () {
    $cli = cli()->withApi(api()->on('POST', 'v1/domains/acme.com/records', fixture('record-create-201.json'), 201));

    $result = $cli->run('domain', 'add', 'acme.com', 'www.acme.com', 'A', '203.0.113.10', '--ttl', '3600');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Added www.acme.com A 203.0.113.10')
        ->and($result->stdout)->toContain('unolia watch record 88231')
        ->and($cli->api()->lastCall()['body'])->toBe([
            'name' => 'www.acme.com', 'type' => 'A', 'value' => '203.0.113.10', 'ttl' => 3600,
        ]);
});

it('turns @ into the domain itself', function () {
    $cli = cli()->withApi(api()->on('POST', 'v1/domains/acme.com/records', fixture('record-create-201.json'), 201));

    $cli->run('domain', 'add', 'acme.com', '@', 'A', '203.0.113.10');

    expect($cli->api()->lastCall()['body']['name'])->toBe('acme.com');
});

it('prints validation errors and exits 2', function () {
    $result = cli()
        ->withApi(api()->on('POST', 'v1/domains/acme.com/records', fixture('record-create-422.json'), 422))
        ->run('domain', 'add', 'acme.com', 'www.acme.com', 'A', 'nope');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('value: The value must be a valid IPv4 address.');
});

it('asks for what it is missing on a terminal', function () {
    $result = cli()
        ->answers([
            'Domain name' => 'acme.com',
            'Full record name' => 'www.acme.com',
            'Record type' => 'A',
            'Value' => '203.0.113.10',
        ])
        ->withApi(api()->on('POST', 'v1/domains/acme.com/records', fixture('record-create-201.json'), 201))
        ->run('domain', 'add');

    expect($result->exitCode)->toBe(0);
});

it('writes nothing under --dry-run', function () {
    $result = cli()->run('domain', 'add', 'acme.com', 'www.acme.com', 'A', '203.0.113.10', '--dry-run', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['domain'])->toBe('acme.com');
});

it('updates a record', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v1/records/88231', fixture('record-88231-pending.json'))
        ->on('PATCH', 'v1/records/88231', fixture('record-create-201.json')));

    $result = $cli->run('domain', 'update', '88231', 'www.acme.com', '203.0.113.11');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body'])->toBe(['name' => 'www.acme.com', 'value' => '203.0.113.11']);
});

it('refuses an update with nothing to change in a pipe', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/records/88231', fixture('record-88231-pending.json')))
        ->run('domain', 'update', '88231');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('nothing to update');
});

it('shows the record before removing it', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v1/records/88231', fixture('record-88231-pending.json'))
        ->on('DELETE', 'v1/records/88231', []));

    $result = $cli->run('domain', 'remove', '88231', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Removed the record');
});

it('needs a confirmation to remove a record', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/records/88231', fixture('record-88231-pending.json')))
        ->run('domain', 'remove', '88231');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation');
});

it('watches a record until it verifies', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v1/records/88231', fixture('record-88231-pending.json'))
            ->on('GET', 'v1/records/88231', fixture('record-88231-verified.json')))
        ->run('domain', 'watch', '88231');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Record www.acme.com is verified');
});

it('checks once with --once and exits 6 when it is not verified', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/records/88231', fixture('record-88231-pending.json')))
        ->run('domain', 'watch', '88231', '--once');

    expect($result->exitCode)->toBe(6);
});
