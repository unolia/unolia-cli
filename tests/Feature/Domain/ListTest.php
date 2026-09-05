<?php

declare(strict_types=1);
use Tests\Support\CliTester;

it('lists domains as a table when piped', function () {
    $cli = cli()->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')));

    $result = $cli->run('domain', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('DOMAIN')
        ->and($result->stdout)->toContain('acme.com')
        ->and($result->stdout)->toContain('never synced');

    $cli->api()->assertEverythingUsed();
});

it('lists domains as JSON', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->run('domain', 'list', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toHaveCount(2)
        ->and($result->json()[0]['domain'])->toBe('acme.com');
});

it('picks fields with --json', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->run('domain', 'list', '--json=domain');

    expect($result->json()[0])->toBe(['domain' => 'acme.com']);
});

it('accepts the colon spelling', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->run('domain:list', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json())->toHaveCount(2);
});

it('sends the team header when a team is in context', function () {
    $cli = cli()
        ->withApi(api()->on('GET', 'v1/domains', fixture('domains.json')))
        ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);

    $cli->run('domain', 'list', '--json');

    expect($cli->api()->calls())->toHaveCount(1);
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
        ->and($result->stdout)->toContain('No domains yet.');
});
