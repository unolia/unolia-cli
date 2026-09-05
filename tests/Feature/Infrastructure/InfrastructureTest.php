<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function infra(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
}

it('lists incidents', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/incidents?project=12', fixture('incidents.json')))
        ->run('incident', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('downtime')
        ->and($result->stdout)->toContain('marketing.acme.com');
});

it('shows one incident with its origin deployment', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/incidents/77', fixture('incident-77.json')))
        ->run('incident', 'view', '77');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Origin deployment  4812');
});

it('lists the managed servers of a project', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/servers?project=12', fixture('servers.json')))
        ->run('server', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('web-01')
        ->and($result->stdout)->toContain('mysql 8');
});

it('shows one server and its websites', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/servers/61', fixture('server-61.json')))
        ->run('server', 'view', '61');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('203.0.113.10')
        ->and($result->stdout)->toContain('marketing.acme.com');
});

it('shows the environment map', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/projects/12/environments', fixture('project-12-environments.json')))
        ->run('env', 'map');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('anchor marketing.acme.com')
        ->and($result->stdout)->toContain('Managed server');
});

it('shows one environment', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/environments/41', fixture('environment-41.json')))
        ->run('env', 'view', '41');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Anchor');
});

it('lists providers', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/providers', fixture('providers.json')))
        ->run('provider', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Forge');
});

it('shows one provider without its credentials', function () {
    $result = infra()
        ->withApi(api()->on('GET', 'v1/providers/14', fixture('provider-14.json')))
        ->run('provider', 'view', '14', '--json');

    expect($result->json())->not->toHaveKey('credentials')
        ->and($result->json()['sync_status'])->toBe('ok');
});

it('previews a provider sync', function () {
    $result = infra()
        ->withApi(api()->on('POST', 'v1/providers/14/sync', fixture('provider-sync-dry-run.json')))
        ->run('provider', 'sync', '14', '--dry-run', '--json');

    expect($result->json()['job'])->toBe('SynchronizeManagedHostingProvider');
});

it('queues a provider sync and waits for it to land', function () {
    $result = infra()
        ->withApi(api()
            ->on('GET', 'v1/providers/14', fixture('provider-14.json'))
            ->on('POST', 'v1/providers/14/sync', fixture('provider-sync-202.json'))
            ->on('GET', 'v1/providers/14', fixture('provider-14-synced.json')))
        ->run('provider', 'sync', '14', '--wait');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Synced Forge');
});
