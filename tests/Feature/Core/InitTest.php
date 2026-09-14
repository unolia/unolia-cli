<?php

declare(strict_types=1);

it('links this directory from the git remote', function () {
    $cli = cli()
        ->withGitRemote()
        ->withApi(api()
            ->on('GET', 'v2/resolve', fixture('resolve-exact.json'))
            ->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $result = $cli->run('init');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Wrote .unolia/config.json (team acme, project 12, website 118');

    expect($cli->home->readJson('.unolia/config.json'))->toBe([
        'team' => 'acme',
        'project' => 12,
        'website' => 118,
        'environments' => ['production' => 118],
    ]);
});

it('asks which website when several match', function () {
    $cli = cli()
        ->withGitRemote()
        ->answers(['Which website' => '121'])
        ->withApi(api()
            ->on('GET', 'v2/resolve', fixture('resolve-multiple.json'))
            ->on('GET', 'v2/websites/121', fixture('website-118.json')));

    $result = $cli->run('init');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.unolia/config.json')['website'])->toBe(121)
        ->and($cli->home->readJson('.unolia/config.json')['environments'])
        ->toBe(['production' => 118, 'staging' => 121]);
});

it('exits 2 with candidates when nothing can be chosen in a pipe', function () {
    $result = cli()
        ->withGitRemote()
        ->withApi(api()->on('GET', 'v2/resolve', fixture('resolve-multiple.json')))
        ->run('init');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --website')
        ->and($result->stderr)->toContain('marketing.acme.com');
});

it('takes --website without a git remote', function () {
    $cli = cli()->withApi(api()->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $result = $cli->run('init', '--website', '118', '--environment', 'staging=121');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.unolia/config.json')['environments'])->toBe(['staging' => 121]);
});

it('refuses to overwrite without --force', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'website' => 118])
        ->run('init', '--website', '118');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('already exists');
});

it('writes nothing under --dry-run', function () {
    $cli = cli()->withApi(api()->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $result = $cli->run('init', '--website', '118', '--dry-run', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['website'])->toBe(118)
        ->and($cli->home->readJson('.unolia/config.json'))->toBeNull();
});

it('adds local.json to gitignore', function () {
    $cli = cli()
        ->withGitRemote()
        ->withApi(api()
            ->on('GET', 'v2/resolve', fixture('resolve-exact.json'))
            ->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $cli->home->write('.gitignore', "/vendor\n");
    mkdir($cli->home->path('.git'));

    $result = $cli->run('init');

    expect($cli->home->read('.gitignore'))->toBe("/vendor\n.unolia/local.json\n")
        ->and($result->stdout)->toContain('Added .unolia/local.json to .gitignore');
});

it('points at --team when the website id lives in another team', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/websites/999', fixture('error-404.json'), 404))
        ->run('init', '--website', '999', '--team', 'acme');

    expect($result->exitCode)->toBe(4)
        ->and($result->stderr)->toContain('no website 999 in team acme')
        ->and($result->stderr)->toContain('--team');
});
