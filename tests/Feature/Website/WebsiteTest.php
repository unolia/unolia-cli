<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function site(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
}

it('lists the websites of the linked project', function () {
    $cli = site()->withApi(api()->on('GET', 'v2/websites?project=12', fixture('websites.json')));

    $result = $cli->run('website', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('marketing.acme.com')
        ->and($result->stdout)->toContain('Forge');
});

it('widens to every project with --all-projects', function () {
    $cli = site()->withApi(api()->on('GET', 'v2/websites', fixture('websites.json')));

    $cli->run('website', 'list', '--all-projects');

    expect($cli->api()->lastCall()['query'])->not->toHaveKey('project');
});

it('shows one website', function () {
    $result = site()
        ->withApi(api()->on('GET', 'v2/websites/118', fixture('website-118.json')))
        ->run('website', 'view');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Domain       marketing.acme.com')
        ->and($result->stdout)->toContain('PHP          8.3');
});

it('resolves a website by domain', function () {
    $cli = site()->withApi(api()
        ->on('GET', 'v2/websites?q=staging.acme.dev', fixture('websites.json'))
        ->on('GET', 'v2/websites/121', fixture('website-118.json')));

    $result = $cli->run('website', 'view', 'staging.acme.dev', '--json');

    expect($result->exitCode)->toBe(0);
});

it('lists the deployments of a website', function () {
    $result = site()
        ->withApi(api()->on('GET', 'v2/websites/118/deployments', fixture('deployments.json')))
        ->run('website', 'deployments');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('4812')
        ->and($result->stdout)->toContain('3f9c2e1');
});

it('prints the log of the latest deployment', function () {
    $result = site()
        ->withApi(api()
            ->on('GET', 'v2/websites/118/deployments?per_page=1', fixture('deployments.json'))
            ->on('GET', 'v2/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('website', 'logs');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('php artisan migrate --force');
});

it('lists the domains of a website', function () {
    $result = site()
        ->withApi(api()->on('GET', 'v2/websites/118/domains', fixture('website-118-domains.json')))
        ->run('website', 'domains', '--json');

    expect($result->json())->toHaveCount(2)
        ->and($result->json()[0]['ssl_status'])->toBe('active');
});

it('shows the environment keys and not the values', function () {
    $cli = site()->withApi(api()->on('GET', 'v2/websites/118/env?keys_only=1', fixture('website-118-env-keys.json')));

    $result = $cli->run('website', 'env');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('APP_KEY')
        ->and($result->stdout)->not->toContain('base64:');
});

it('refuses to print live credentials into a pipe without --yes', function () {
    $result = site()->run('website', 'env', '--values');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('confirmation');
});

it('prints the values when asked out loud', function () {
    $result = site()
        ->withApi(api()->on('GET', 'v2/websites/118/env', fixture('website-118-env.json')))
        ->run('website', 'env', '--values', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('APP_KEY=base64:secret');
});
