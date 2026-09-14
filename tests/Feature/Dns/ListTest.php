<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function zoned(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12]);
}

it('lists the records of the project zone with bare dns, names relative to the zone', function () {
    $cli = zoned()->withApi(api()
        ->on('GET', 'v2/domains?project=12', fixture('domains-one.json'))
        ->on('GET', 'v2/domains/acme.com/records', fixture('records.json')));

    $result = $cli->run('dns');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('@')
        ->and($result->stdout)->toContain('www')
        ->and($result->stdout)->not->toContain('www.acme.com')
        ->and($result->stdout)->toContain('10 mail.acme.com.')
        ->and($result->stdout)->not->toContain('10 10 mail')
        ->and($result->stdout)->toContain('pending')
        ->and($result->stdout)->toContain('3 records');
    $cli->api()->assertEverythingUsed();
});

it('puts the apex first and keeps full values in the data faces', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')))
        ->run('dns', 'list', 'acme.com', '--json');

    expect($result->json())->toHaveCount(3)
        ->and($result->json()[0]['name'])->toBe('www.acme.com');

    $table = cli()
        ->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')))
        ->run('dns', 'acme.com');

    $lines = array_values(array_filter(explode("\n", $table->stdout), fn (string $line): bool => str_starts_with($line, '#')));

    expect($lines[0])->toContain('@')
        ->and($lines[2])->toContain('www');
});

it('filters by type and by name, on the server and again locally', function () {
    $cli = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')));

    $result = $cli->run('dns', 'acme.com', '--type', 'mx', '--json');

    expect($cli->api()->lastCall()['query'])->toMatchArray(['type' => 'MX'])
        ->and($result->json())->toHaveCount(1)
        ->and($result->json()[0]['type'])->toBe('MX');

    $cli = cli()->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')));
    $byName = $cli->run('dns', 'acme.com', '--name', 'www', '--json');

    expect($cli->api()->lastCall()['query'])->toMatchArray(['name' => 'www.acme.com'])
        ->and($byName->json())->toHaveCount(1);
});

it('is reachable as domain records', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')))
        ->run('domain', 'records', 'acme.com', '--json');

    expect($result->json())->toHaveCount(3);
});

it('asks which zone when the project has several, and exits 2 in a pipe', function () {
    $piped = zoned()
        ->withApi(api()->on('GET', 'v2/domains?project=12', fixture('domains.json')))
        ->run('dns');

    expect($piped->exitCode)->toBe(2)
        ->and($piped->stderr)->toContain('several zones')
        ->and($piped->stderr)->toContain('acme.com, acme.dev');

    $asked = zoned()
        ->answers(['Which zone?' => 'acme.dev'])
        ->withApi(api()
            ->on('GET', 'v2/domains?project=12', fixture('domains.json'))
            ->on('GET', 'v2/domains/acme.dev', fixture('domain-example-com.json'))
            ->on('GET', 'v2/domains/acme.dev/records', fixture('records-none.json')))
        ->run('dns');

    expect($asked->exitCode)->toBe(0)
        ->and($asked->stdout)->toContain('acme.dev has no record.');
});

it('says what to do when there is no zone at all', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains', ['data' => [], 'meta' => ['last_page' => 1]]))
        ->run('dns');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('no zone to work on')
        ->and($result->stderr)->toContain('--zone');
});

it('exports a zone file', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains/acme.com/records', fixture('records.json')))
        ->run('dns', 'export', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('$ORIGIN acme.com.')
        ->and($result->stdout)->toMatch('/^@ +3600 +IN A +203\.0\.113\.10$/m')
        ->and($result->stdout)->toMatch('/^@ +IN MX +10 mail\.acme\.com\.$/m')
        ->and($result->stdout)->toMatch('/^www +3600 +IN A +203\.0\.113\.10$/m');
});

it('does not double a priority the provider already put in the value', function () {
    $records = fixture('records.json');
    $records['data'][1]['value'] = '10 mail.acme.com.';

    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains/acme.com/records', $records))
        ->run('dns', 'acme.com');

    expect($result->stdout)->toContain('10 mail.acme.com.')
        ->and($result->stdout)->not->toContain('10 10 mail');
});
