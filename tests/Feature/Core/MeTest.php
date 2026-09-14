<?php

declare(strict_types=1);

it('shows the current user', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json')))
        ->run('me');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Kind')->toContain('user')
        ->and($result->stdout)->toContain('eser@acme.com');
});

it('shows a team token', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v2/current/token', fixture('current-token-team.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-team.json')))
        ->run('me', '--json');

    expect($result->json()['kind'])->toBe('team')
        ->and($result->json()['name'])->toBe('Acme')
        ->and($result->json()['scopes'])->toBe(['deployment:read', 'deployment:write']);
});
