<?php

declare(strict_types=1);

use Tests\Support\CliTester;

it('prefers the flag over the environment, the config and the settings', function () {
    $cli = cli()
        ->withConfig(['team' => 'from-config', 'website' => 118])
        ->env(['UNOLIA_TEAM' => 'from-env'])
        ->withApi(api()
            ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $result = $cli->run('status', '--team', 'from-flag', '--json');

    expect($result->json()['team'])->toBe('from-flag')
        ->and($result->json()['sources']['team'])->toBe('flag');
});

it('falls back to the environment, then the config file', function () {
    $withEnv = cli()
        ->withConfig(['team' => 'from-config'])
        ->env(['UNOLIA_TEAM' => 'from-env'])
        ->withApi(api()
            ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json')))
        ->run('status', '--json');

    expect($withEnv->json()['team'])->toBe('from-env')
        ->and($withEnv->json()['sources']['team'])->toBe('UNOLIA_TEAM');

    $withConfig = cli()
        ->withConfig(['team' => 'from-config'])
        ->withApi(api()
            ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json')))
        ->run('status', '--json');

    expect($withConfig->json()['team'])->toBe('from-config')
        ->and($withConfig->json()['sources']['team'])->toBe('config');
});

it('resolves a website from the git remote when nothing is linked', function () {
    $result = cli()
        ->withGitRemote()
        ->withApi(api()
            ->on('GET', 'v2/resolve', fixture('resolve-exact.json'))
            ->on('GET', 'v2/websites/118', fixture('website-118.json')))
        ->run('website', 'view', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['id'])->toBe(118);
});

it('turns a domain into a website id', function () {
    $cli = cli()->withApi(api()
        ->on('GET', 'v2/websites?q=staging.acme.dev', fixture('websites.json'))
        ->on('GET', 'v2/websites/121', fixture('website-118.json')));

    $result = $cli->run('website', 'view', '--website', 'staging.acme.dev', '--json');

    expect($result->exitCode)->toBe(0);
});

it('exits 4 for a domain nothing matches', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/websites?q=nope.example', ['data' => [], 'meta' => ['last_page' => 1]]))
        ->run('website', 'view', '--website', 'nope.example');

    expect($result->exitCode)->toBe(4)
        ->and($result->stderr)->toContain('no website called nope.example');
});

it('says this directory is not linked when there is nothing to go on', function () {
    $result = CliTester::make()->withToken()->run('website', 'view');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not linked to a website')
        ->and($result->stderr)->toContain('unolia init');
});

it('survives an API that has no resolve endpoint yet', function () {
    $result = cli()
        ->withGitRemote()
        ->withApi(api()->on('GET', 'v2/resolve', fixture('error-404.json'), 404))
        ->run('website', 'view');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not linked to a website');
});
