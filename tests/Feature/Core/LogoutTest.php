<?php

declare(strict_types=1);

use Tests\Support\CliTester;

it('logs out and forgets the token', function () {
    $cli = cli()->withApi(api()->on('DELETE', 'logout', []));

    $result = $cli->run('logout');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Logged out of app.unolia.com')
        ->and(json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/hosts.json'), true))->toBe([]);
});

it('exits 3 when there is nothing to log out of', function () {
    $result = CliTester::make()->run('logout');

    expect($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('not logged in');
});

it('drops the local token when the API already revoked it', function () {
    $cli = cli()->withApi(api()->on('DELETE', 'logout', fixture('error-401.json'), 401));

    $result = $cli->run('logout');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('only the local copy');
});

it('needs --force when the API fails for another reason', function () {
    $result = cli()
        ->withApi(api()->on('DELETE', 'logout', ['message' => 'boom'], 500))
        ->run('logout');

    expect($result->exitCode)->toBe(1);

    $result = cli()
        ->withApi(api()->on('DELETE', 'logout', ['message' => 'boom'], 500))
        ->run('logout', '--force');

    expect($result->exitCode)->toBe(0);
});

it('leaves a token that comes from the environment alone', function () {
    $cli = cli()->env(['UNOLIA_TOKEN' => 'ci-token']);

    $result = $cli->run('logout');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('comes from UNOLIA_TOKEN')
        ->and($cli->api()->calls())->toBe([])
        ->and(json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/hosts.json'), true))->toHaveKey('app.unolia.com');
});
