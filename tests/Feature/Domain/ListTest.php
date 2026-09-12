<?php

declare(strict_types=1);

use Tests\Support\CliTester;

it('lists the zones of the linked project on the table face', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12])->withApi(api()->on('GET', 'v1/domains?project=12', fixture('domains.json')));

    $result = $cli->run('domain', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('DOMAIN')
        ->and($result->stdout)->toContain('acme.com')
        ->and($result->stdout)->toContain('Cloudflare')
        ->and($result->stdout)->toContain('point elsewhere')
        ->and($result->stdout)->toContain('2 zones')
        ->and($result->stdout)->not->toContain('TEAM');
    $cli->api()->assertEverythingUsed();
});

it('is reachable as domains and widens with --all-projects and --all-teams', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12])->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')));

    $cli->run('domains', '--all-projects');

    expect($cli->api()->lastCall()['query'])->not->toHaveKey('project');

    $cli = cli()->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')));
    $result = $cli->run('domains', '--all-teams');

    expect($cli->api()->lastCall()['query'])->toMatchArray(['all_teams' => '1'])
        ->and($result->stdout)->toContain('TEAM');
});

it('lists zones as JSON and picks fields', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->run('domain', 'list', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toHaveCount(2)
        ->and($result->json()[0]['domain'])->toBe('acme.com');

    $picked = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->run('domain:list', '--json=domain');

    expect($picked->json()[0])->toBe(['domain' => 'acme.com']);
});

it('exits 3 without a token', function () {
    $result = CliTester::make()->run('domain', 'list', '--json');

    expect($result->exitCode)->toBe(3)
        ->and($result->errorJson()['error']['code'])->toBe('unauthenticated');
});

it('reports an empty list', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains', ['data' => [], 'meta' => ['last_page' => 1]]))
        ->run('domain', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('No zones here yet.');
});

it('shows one zone as a page, and the project zone by default', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v1/domains/acme.com', fixture('domain-example-com.json'))
            ->on('GET', 'v1/domains/acme.com/records', fixture('records.json'))
            ->on('GET', 'v1/issues', fixture('issues.json')))
        ->run('domain', 'view', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('acme.com')
        ->and($result->stdout)->toContain('ns1.unolia.com')
        ->and($result->stdout)->toContain('the world points at the provider')
        ->and($result->stdout)->toContain('3 records')
        ->and($result->stdout)->toContain('Missing DMARC')
        ->and($result->stdout)->toContain('unolia dns check acme.com');

    $inferred = cli()->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()
            ->on('GET', 'v1/domains?project=12', fixture('domains-one.json'))
            ->on('GET', 'v1/domains/acme.com', fixture('domain-example-com.json'))
            ->on('GET', 'v1/domains/acme.com/records', fixture('records.json'))
            ->on('GET', 'v1/issues', fixture('issues.json')))
        ->run('domain', 'view', '--json', 'nameservers');

    expect($inferred->json()['nameservers']['ok'])->toBeTrue();
});
