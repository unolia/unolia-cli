<?php

declare(strict_types=1);

use Tests\Support\CliTester;
use Tests\Support\FakeApi;

function tokenApi(): FakeApi
{
    return api()
        ->on('GET', 'v1/current/token', fixture('current-token-user.json'))
        ->on('GET', 'v1/current/authenticated', fixture('current-authenticated-user.json'));
}

it('logs in with a token option', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $result = $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Logged in to app.unolia.com as eser (user token)');

    $hosts = json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/hosts.json'), true);

    expect($hosts['app.unolia.com']['token'])->toBe('utk_test_9f2c1b7a')
        ->and($hosts['app.unolia.com']['kind'])->toBe('user');
});

it('writes hosts.json with mode 0600', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    $mode = fileperms($cli->home->home.'/.config/unolia/hosts.json') & 0777;

    expect(decoct($mode))->toBe('600');
});

it('reads the token from stdin', function () {
    $result = CliTester::make()
        ->withApi(tokenApi())
        ->stdin("utk_test_9f2c1b7a\n")
        ->run('login', '--with-token');

    expect($result->exitCode)->toBe(0);
});

it('exits 2 without a token in the pipe face', function () {
    $result = CliTester::make()->run('login');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --token');
});

it('exits 3 when the token is rejected', function () {
    $result = CliTester::make()
        ->withApi(api()->on('GET', 'v1/current/token', fixture('error-401.json'), 401))
        ->run('login', '--token', 'nope');

    expect($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('rejected');
});

it('says you are already logged in', function () {
    $result = cli()
        ->withApi(tokenApi())
        ->run('login');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Already logged in as eser');
});

it('signs in with an email and password, retrying after a two factor code', function () {
    $cli = CliTester::make()
        ->withApi(api()
            ->on('POST', 'login', fixture('login-422-2fa.json'), 422)
            ->on('POST', 'login', fixture('login-201.json'), 201)
            ->on('GET', 'v1/current/token', fixture('current-token-user.json'))
            ->on('GET', 'v1/current/authenticated', fixture('current-authenticated-user.json')))
        ->answers([
            'How do you want to authenticate' => 'password',
            'Email' => 'eser@acme.com',
            'Password' => 'secret',
            'Two factor code' => '123456',
        ]);

    $result = $cli->run('login');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Logged in to app.unolia.com as eser');

    $cli->api()->assertEverythingUsed();
});

it('pastes a token in the TTY face', function () {
    $result = CliTester::make()
        ->withApi(tokenApi())
        ->answers(['How do you want to authenticate' => 'paste', 'Token' => 'utk_test_9f2c1b7a'])
        ->run('login');

    expect($result->exitCode)->toBe(0);
});

it('answers with a record in the JSON face', function () {
    $result = CliTester::make()
        ->withApi(tokenApi())
        ->run('login', '--token', 'utk_test_9f2c1b7a', '--json');

    expect($result->json()['kind'])->toBe('user')
        ->and($result->json()['name'])->toBe('eser')
        ->and($result->json())->not->toHaveKey('token');
});

it('does not throw the stored token away when the API fails for another reason', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/current/token', ['message' => 'boom'], 500))
        ->run('login');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->not->toContain('fresh login');
});
