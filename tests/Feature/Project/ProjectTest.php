<?php

declare(strict_types=1);

it('lists projects', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/projects', fixture('projects.json')))
        ->run('project', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Marketing site')
        ->and($result->stdout)->toContain('Docs');
});

it('shows one project with its environments', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()
            ->on('GET', 'v2/projects/12', fixture('project-12.json'))
            ->on('GET', 'v2/projects/12/environments', fixture('project-12-environments.json')))
        ->run('project', 'view');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Open issues')
        ->and($result->stdout)->toContain('marketing.acme.com');
});

it('shows what the git remote maps to', function () {
    $result = cli()
        ->withGitRemote()
        ->withApi(api()->on('GET', 'v2/resolve', fixture('resolve-exact.json')))
        ->run('project', 'resolve');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('acme/marketing')
        ->and($result->stdout)->toContain('exact')
        ->and($result->stdout)->toContain('production');
});

it('exits 4 when nothing matches', function () {
    $result = cli()
        ->withGitRemote('git@github.com:acme/unknown.git')
        ->withApi(api()->on('GET', 'v2/resolve', fixture('resolve-none.json')))
        ->run('project', 'resolve');

    expect($result->exitCode)->toBe(0);
});

it('switches project and drops a website from elsewhere', function () {
    $cli = cli()
        ->withConfig(['team' => 'acme', 'project' => 99, 'website' => 118, 'environments' => ['production' => 118]])
        ->withApi(api()
            ->on('GET', 'v2/projects/13', ['data' => ['id' => 13, 'name' => 'Docs', 'team' => ['slug' => 'acme']]])
            ->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $result = $cli->run('project', 'switch', '13');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.unolia/config.json'))->toBe(['team' => 'acme', 'project' => 13])
        ->and($result->stdout)->toContain('belonged to another project');
});

it('keeps a website that belongs to the new project', function () {
    $cli = cli()
        ->withConfig(['team' => 'acme', 'project' => 99, 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v2/projects/12', fixture('project-12.json'))
            ->on('GET', 'v2/websites/118', fixture('website-118.json')));

    $cli->run('project', 'switch', '12');

    expect($cli->home->readJson('.unolia/config.json')['website'])->toBe(118);
});
