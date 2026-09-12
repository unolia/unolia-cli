<?php

declare(strict_types=1);

it('lists the teams with the current one marked', function () {
    $result = cli()
        ->withConfig(['team' => 'acme'])
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
        ->run('team', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('acme')
        ->and($result->stdout)->toContain('*');
});

it('is reachable as teams, which v1 shipped', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
        ->run('teams', '--json');

    expect($result->json())->toHaveCount(2);
});

it('switches the default team', function () {
    $cli = cli()->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')));

    $result = $cli->run('team', 'switch', 'acme');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Now working in team acme');

    $settings = json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/config.json'), true);

    expect($settings['default_team'])->toBe('acme');
});

it('switches by id or by name and stores the slug', function () {
    $cli = cli()->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')));

    $result = $cli->run('team', 'switch', '3');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Now working in team acme')
        ->and(json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/config.json'), true)['default_team'])->toBe('acme');

    $byName = cli()->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))->run('team', 'switch', 'Eser');

    expect($byName->stdout)->toContain('Now working in team personal');
});

it('switches only this directory with --local', function () {
    $cli = cli()
        ->withConfig(['project' => 12])
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')));

    $cli->run('team', 'switch', 'acme', '--local');

    expect($cli->home->readJson('.unolia/config.json'))->toBe(['project' => 12, 'team' => 'acme']);
});

it('exits 4 for a team it cannot reach', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
        ->run('team', 'switch', 'other');

    expect($result->exitCode)->toBe(4)
        ->and($result->stderr)->toContain('no team called other');
});

it('prints the team tokens page', function () {
    $result = cli()
        ->withConfig(['team' => 'acme'])
        ->run('team', 'tokens', '--print');

    expect(trim($result->stdout))->toBe('https://app.unolia.com/acme/team/api-tokens');
});
