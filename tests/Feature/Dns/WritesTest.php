<?php

declare(strict_types=1);

use Tests\Support\CliTester;

function oneZone(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()->on('GET', 'v2/domains?project=12', fixture('domains-one.json')));
}

it('adds a record in zone file order, relative to the project zone, and follows it with --wait', function () {
    $cli = oneZone();
    $cli->api()
        ->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201)
        ->on('GET', 'v2/records/88231', fixture('record-88231-verified.json'));

    $result = $cli->run('dns', 'add', 'www', 'A', '203.0.113.10', '--ttl', '3600', '--wait');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Record www.acme.com is verified')
        ->and($cli->api()->calls()[1]['body'])->toBe([
            'name' => 'www.acme.com', 'type' => 'A', 'value' => '203.0.113.10', 'ttl' => 3600,
        ]);
});

it('hands the record back at once in a pipe, with the watch hint', function () {
    $cli = oneZone();
    $cli->api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201);

    $result = $cli->run('dns', 'add', 'www', 'A', '203.0.113.10');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Added www A 203.0.113.10')
        ->and($result->stdout)->toContain('unolia dns watch 88231');
});

it('turns @ into the zone, accepts a full hostname, sends the priority, and picks the zone from the hostname', function () {
    $cli = oneZone();
    $cli->api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201);
    $cli->run('dns', 'add', '@', 'MX', 'mail.acme.com', '--priority', '10');

    expect($cli->api()->lastCall()['body'])->toBe(['name' => 'acme.com', 'type' => 'MX', 'value' => 'mail.acme.com', 'priority' => 10]);

    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12])->withApi(api()
        ->on('GET', 'v2/domains?project=12', fixture('domains.json'))
        ->on('POST', 'v2/domains/acme.dev/records', fixture('record-create-201.json'), 201));
    $cli->run('dns', 'add', 'www.acme.dev.', 'A', '203.0.113.10');

    expect($cli->api()->lastCall()['path'])->toBe('v2/domains/acme.dev/records')
        ->and($cli->api()->lastCall()['body']['name'])->toBe('www.acme.dev');
});

it('takes --zone anywhere and the old domain add spelling with the zone first', function () {
    $cli = cli()->withApi(api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201));
    $cli->run('dns', 'add', 'www', 'A', '203.0.113.10', '--zone', 'acme.com');

    expect($cli->api()->calls())->toHaveCount(1)
        ->and($cli->api()->lastCall()['body']['name'])->toBe('www.acme.com');

    $old = cli()->withApi(api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201));
    $old->run('domain', 'add', 'acme.com', 'www.acme.com', 'A', '203.0.113.10');

    expect($old->api()->lastCall()['body']['name'])->toBe('www.acme.com');
});

it('prints validation errors and exits 2', function () {
    $result = cli()
        ->withApi(api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-422.json'), 422))
        ->run('dns', 'add', 'www', 'A', 'nope', '--zone', 'acme.com');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('value: The value must be a valid IPv4 address.');
});

it('asks for what it is missing on a terminal', function () {
    $cli = oneZone()->answers([
        'Record name' => 'www',
        'Record type' => 'A',
        'Value' => '203.0.113.10',
    ]);
    $cli->api()->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201);

    $result = $cli->run('dns', 'add', '--no-progress');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body']['name'])->toBe('www.acme.com');
});

it('writes nothing under --dry-run', function () {
    $result = cli()->run('dns', 'add', 'www', 'A', '203.0.113.10', '--zone', 'acme.com', '--dry-run', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toBe(['zone' => 'acme.com', 'name' => 'www.acme.com', 'type' => 'A', 'value' => '203.0.113.10']);
});

it('set creates the record when there is none of that name and type', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v2/domains/acme.com/records?name=blog.acme.com&type=A', fixture('records-none.json'))
        ->on('POST', 'v2/domains/acme.com/records', fixture('record-create-201.json'), 201));

    $result = $cli->run('dns', 'set', 'blog', 'A', '203.0.113.10', '--zone', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Added')
        ->and($cli->api()->lastCall()['body'])->toBe(['name' => 'blog.acme.com', 'type' => 'A', 'value' => '203.0.113.10']);
});

it('set replaces the value of the one existing record after asking, and says so when nothing changes', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json'))
        ->on('PATCH', 'v2/records/88231', fixture('record-create-201.json')));

    $result = $cli->run('dns', 'set', 'www', 'A', '203.0.113.11', '--zone', 'acme.com', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Replaced')
        ->and($cli->api()->lastCall()['body'])->toBe(['value' => '203.0.113.11']);

    $refused = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json')))
        ->run('dns', 'set', 'www', 'A', '203.0.113.11', '--zone', 'acme.com');

    expect($refused->exitCode)->toBe(2)
        ->and($refused->stderr)->toContain('Replace it with 203.0.113.11?');

    $same = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json')))
        ->run('dns', 'set', 'www', 'A', '203.0.113.10', '--zone', 'acme.com');

    expect($same->exitCode)->toBe(0)
        ->and($same->stdout)->toContain('www A is already 203.0.113.10');
});

it('set refuses when several records share the name and type', function () {
    $two = fixture('records-www.json');
    $two['data'][] = ['id' => 88240, 'name' => 'www.acme.com', 'type' => 'A', 'value' => '203.0.113.12', 'ttl' => 300, 'state' => 'verified'];

    $result = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records', $two))
        ->run('dns', 'set', 'www', 'A', '203.0.113.11', '--zone', 'acme.com');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('has 2 records')
        ->and($result->stderr)->toContain('#88231')
        ->and($result->stderr)->toContain('#88240');
});

it('edits a record by id, and by name and type', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json'))
        ->on('PATCH', 'v2/records/88231', fixture('record-create-201.json')));

    $result = $cli->run('dns', 'edit', '88231', '--value', '203.0.113.11', '--ttl', '300');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Changed www A')
        ->and($cli->api()->lastCall()['body'])->toBe(['name' => 'www.acme.com', 'value' => '203.0.113.11', 'ttl' => 300]);

    $cli = cli()->withApi(api()
        ->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json'))
        ->on('PATCH', 'v2/records/88231', fixture('record-create-201.json')));
    $cli->run('dns', 'edit', 'www', 'A', '--name', 'web', '--zone', 'acme.com');

    expect($cli->api()->lastCall()['body'])->toBe(['name' => 'web.acme.com', 'value' => '203.0.113.10']);

    $old = cli()->withApi(api()
        ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json'))
        ->on('PATCH', 'v2/records/88231', fixture('record-create-201.json')));
    $old->run('domain', 'update', '88231', '--ttl', '300');

    expect($old->api()->lastCall()['body']['ttl'])->toBe(300);
});

it('refuses an edit with nothing to change in a pipe', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/records/88231', fixture('record-88231-pending.json')))
        ->run('dns', 'edit', '88231');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('nothing to change');
});

it('removes a record after showing it, by name too, and needs a confirmation', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json'))
        ->on('DELETE', 'v2/records/88231', []));

    $result = $cli->run('dns', 'remove', '88231', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Removed www A 203.0.113.10 from acme.com');

    $byName = cli()->withApi(api()
        ->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json'))
        ->on('DELETE', 'v2/records/88231', []))
        ->run('dns', 'remove', 'www', 'A', '--zone', 'acme.com', '--yes');

    expect($byName->exitCode)->toBe(0);

    $asked = cli()
        ->withApi(api()->on('GET', 'v2/records/88231', fixture('record-88231-pending.json')))
        ->run('dns', 'remove', '88231');

    expect($asked->exitCode)->toBe(2)
        ->and($asked->stdout)->toContain('#88231')
        ->and($asked->stdout)->toContain('203.0.113.10')
        ->and($asked->stderr)->toContain('needs a confirmation')
        ->and($asked->stderr)->toContain('Remove www A 203.0.113.10 from acme.com?');
});

it('says when a name matches no record, or several', function () {
    $none = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records-none.json')))
        ->run('dns', 'remove', 'nope', '--zone', 'acme.com');

    expect($none->exitCode)->toBe(4)
        ->and($none->stderr)->toContain('no nope record in acme.com');

    $several = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')))
        ->run('dns', 'remove', '@', '--zone', 'acme.com');

    expect($several->exitCode)->toBe(2)
        ->and($several->stderr)->toContain('@ matches 2 records in acme.com')
        ->and($several->stderr)->toContain('#88232 MX');
});

it('watches a record until it verifies, by id or by name, and once with --once', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json'))
            ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json'))
            ->on('GET', 'v2/records/88231', fixture('record-88231-verified.json')))
        ->run('dns', 'watch', '88231');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Record www.acme.com is verified');

    $once = cli()
        ->withApi(api()
            ->on('GET', 'v2/domains/acme.com/records?name=www.acme.com&type=A', fixture('records-www.json'))
            ->on('GET', 'v2/records/88231', fixture('record-88231-pending.json')))
        ->run('domain', 'watch', 'www', 'A', '--once', '--zone', 'acme.com');

    expect($once->exitCode)->toBe(6);
});
