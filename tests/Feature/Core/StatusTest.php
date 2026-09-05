<?php

declare(strict_types=1);

use Tests\Support\CliTester;

it('shows where this directory points', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withGitRemote()
        ->withApi(api()
            ->on('GET', 'v1/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v1/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('GET', 'v1/projects/12', fixture('project-12.json'))
            ->on('GET', 'v1/websites/118', fixture('website-118.json')))
        ->run('status');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Host     app.unolia.com')
        ->and($result->stdout)->toContain('eser (user token, scopes *)')
        ->and($result->stdout)->toContain('12 Marketing site')
        ->and($result->stdout)->toContain('118 marketing.acme.com')
        ->and($result->stdout)->toContain('acme/marketing @ main (3f9c2e1)')
        ->and($result->stdout)->toContain('.unolia/config.json');
});

it('says what is missing without prompting', function () {
    $result = CliTester::make()->run('status');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('not logged in, run unolia login')
        ->and($result->stdout)->toContain('not linked, run unolia init');
});

it('answers with an object and its sources in the JSON face', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v1/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v1/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('GET', 'v1/projects/12', fixture('project-12.json'))
            ->on('GET', 'v1/websites/118', fixture('website-118.json')))
        ->run('status', '--json');

    expect($result->json()['website'])->toBe(118)
        ->and($result->json()['team'])->toBe('acme')
        ->and($result->json()['sources']['website'])->toBe('config');
});

it('exits 3 when the token is no longer valid', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/current/token', fixture('error-401.json'), 401))
        ->run('status');

    expect($result->exitCode)->toBe(3);
});
