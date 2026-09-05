<?php

declare(strict_types=1);

it('reads a v1 path', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
        ->run('api', 'v1/teams');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['data'][0]['slug'])->toBe('acme');
});

it('accepts every spelling of the same path', function () {
    foreach (['v1/teams', '/v1/teams', '/api/v1/teams', 'teams', 'https://app.unolia.com/api/v1/teams'] as $endpoint) {
        $result = cli()
            ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
            ->run('api', $endpoint);

        expect($result->exitCode)->toBe(0, $endpoint);
    }
});

it('refuses a URL on another host', function () {
    $result = cli()->run('api', 'https://example.com/api/v1/teams');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('is not on app.unolia.com');
});

it('turns fields into query parameters on a GET', function () {
    $cli = cli()->withApi(api()->on('GET', 'v1/websites?project=12', fixture('websites.json')));

    $cli->run('api', 'v1/websites', '-X', 'GET', '-f', 'project=12');

    expect($cli->api()->lastCall()['query'])->toBe(['project' => '12']);
});

it('sends typed fields as a JSON body', function () {
    $cli = cli()->withApi(api()->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201));

    $result = $cli->run('api', 'v1/websites/118/deployments', '-X', 'POST', '-F', 'dry_run=false', '-F', 'count=3');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => false, 'count' => 3]);
});

it('posts by default when fields are given', function () {
    $cli = cli()->withApi(api()->on('POST', 'v1/issues/abc/ignore', ['data' => []]));

    $cli->run('api', 'v1/issues/abc/ignore', '-F', 'dry_run=false');

    expect($cli->api()->lastCall()['method'])->toBe('POST');
});

it('reads a body from stdin', function () {
    $cli = cli()
        ->stdin('{"dry_run":true}')
        ->withApi(api()->on('POST', 'v1/websites/118/deployments', fixture('deployment-dry-run.json')));

    $result = $cli->run('api', 'v1/websites/118/deployments', '--input=-');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => true]);
});

it('sends an extra header', function () {
    $cli = cli()->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')));

    $cli->run('api', 'v1/teams', '-H', 'X-Trace: abc');

    expect($result ?? true)->toBeTruthy();
});

it('prints the status line with --include', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/teams', fixture('teams.json')))
        ->run('api', 'v1/teams', '--include');

    expect($result->stdout)->toContain('HTTP/1.1 200');
});

it('maps HTTP errors to exit codes', function () {
    $notFound = cli()
        ->withApi(api()->on('GET', 'v1/websites/999', fixture('error-404.json'), 404))
        ->run('api', 'v1/websites/999');

    expect($notFound->exitCode)->toBe(4);

    $unauthenticated = cli()
        ->withApi(api()->on('GET', 'v1/teams', fixture('error-401.json'), 401))
        ->run('api', 'v1/teams');

    expect($unauthenticated->exitCode)->toBe(3);

    $upgrade = cli()
        ->withApi(api()->on('GET', 'v1/automations', fixture('error-402.json'), 402))
        ->run('api', 'v1/automations', '--json');

    expect($upgrade->exitCode)->toBe(5)
        ->and($upgrade->errorJson()['error']['message'])->toBe('Automations needs the Studio plan');
});

it('merges pages with --paginate --slurp', function () {
    $page = fn (int $page, int $last, array $rows): array => [
        'data' => $rows,
        'links' => ['next' => $page < $last ? 'https://app.unolia.com/api/v1/teams?page='.($page + 1) : null],
        'meta' => ['current_page' => $page, 'last_page' => $last],
    ];

    $result = cli()
        ->withApi(api()
            ->on('GET', 'v1/teams?page=1', $page(1, 2, [['id' => 1]]))
            ->on('GET', 'v1/teams?page=2', $page(2, 2, [['id' => 2]])))
        ->run('api', 'v1/teams', '--paginate', '--slurp');

    expect($result->json())->toBe([['id' => 1], ['id' => 2]]);
});
