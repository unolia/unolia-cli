<?php

declare(strict_types=1);
use Tests\Support\CliTester;
use Tests\Support\FakeApi;

function compareApi(): FakeApi
{
    return api()
        ->on('GET', 'v1/websites/118', fixture('website-118.json'))
        ->on('GET', 'v1/servers/61', fixture('server-61.json'))
        ->on('GET', 'v1/projects/12/versions', fixture('project-12-versions.json'));
}

function compareCli(): CliTester
{
    return cli()
        ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118, 'environments' => ['production' => 118]])
        ->withPhp('8.3.12')
        ->withComposer(['laravel/framework' => '12.28.1'])
        ->withNode('22.11.0');
}

it('says everything matches', function () {
    $result = compareCli()->withApi(compareApi())->run('compare', 'local');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('php')
        ->and($result->stdout)->toContain('✓')
        ->and($result->stdout)->toContain('unknown on production');
});

it('exits 1 when PHP differs and suggests configure herd', function () {
    $result = compareCli()
        ->withPhp('8.4.11')
        ->withApi(compareApi())
        ->run('compare', 'local');

    expect($result->exitCode)->toBe(1)
        ->and($result->stdout)->toContain('✗')
        ->and($result->stdout)->toContain('unolia configure herd');
});

it('warns on a patch difference and only fails under --strict', function () {
    $ok = compareCli()
        ->withComposer(['laravel/framework' => '12.28.0'])
        ->withApi(compareApi())
        ->run('compare', 'local');

    expect($ok->exitCode)->toBe(0)
        ->and($ok->stdout)->toContain('!');

    $strict = compareCli()
        ->withComposer(['laravel/framework' => '12.28.0'])
        ->withApi(compareApi())
        ->run('compare', 'local', '--strict');

    expect($strict->exitCode)->toBe(1);
});

it('reports unknown instead of failing when a local tool is missing', function () {
    $result = compareCli()
        ->withPhp(null)
        ->withApi(compareApi())
        ->run('compare', 'local', '--all', '--json');

    $verdicts = array_column($result->json()['components'], 'verdict', 'name');

    expect($result->exitCode)->toBe(0)
        ->and($verdicts['php'])->toBe('unknown');
});

it('answers with components and suggestions in the JSON face', function () {
    $result = compareCli()->withApi(compareApi())->run('compare', 'local', '--json');

    expect($result->json()['target']['website'])->toBe(118)
        ->and($result->json()['summary'])->toContain('match')
        ->and(array_column($result->json()['components'], 'name'))->toBe(['php', 'laravel', 'composer']);
});

it('adds database and node with --all', function () {
    $result = compareCli()->withApi(compareApi())->run('compare', 'local', '--all', '--json');

    expect(array_column($result->json()['components'], 'name'))
        ->toBe(['php', 'laravel', 'composer', 'database', 'node']);
});

it('takes only the components that were named', function () {
    $result = compareCli()->withApi(compareApi())->run('compare', 'local', '--only', 'php', '--json');

    expect(array_column($result->json()['components'], 'name'))->toBe(['php']);
});

it('refuses a component it does not know', function () {
    $result = compareCli()->withApi(compareApi())->run('compare', 'local', '--only', 'rust');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('is not a component');
});

it('compares the versions of a project side by side', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()->on('GET', 'v1/projects/12/versions', fixture('project-12-versions.json')))
        ->run('compare', 'versions');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('laravel/framework')
        ->and($result->stdout)->toContain('ACME/MARKETING')
        ->and($result->stdout)->not->toContain('! laravel/framework');
});
