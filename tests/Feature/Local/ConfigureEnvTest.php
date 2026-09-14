<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function envCli(): CliTester
{
    return cli()->withConfig([
        'team' => 'acme',
        'project' => 12,
        'website' => 118,
        'environments' => ['production' => 118, 'staging' => 121],
    ]);
}

it('adds the missing keys to .env.example', function () {
    $cli = envCli()->withApi(api()->on('GET', 'v2/websites/118/env?keys_only=1', fixture('website-118-env-keys.json')));
    $cli->home->write('.env.example', "APP_ENV=\nAPP_KEY=\n");

    $result = $cli->run('configure', 'env');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->read('.env.example'))
        ->toBe("APP_ENV=\nAPP_KEY=\nDB_CONNECTION=\nDB_HOST=\nDB_PORT=\nMAIL_MAILER=\n")
        ->and($result->stdout)->toContain('Added 4 keys');
});

it('creates the file when it does not exist', function () {
    $cli = envCli()->withApi(api()->on('GET', 'v2/websites/118/env?keys_only=1', fixture('website-118-env-keys.json')));

    $cli->run('configure', 'env');

    expect($cli->home->read('.env.example'))->toContain('APP_ENV=');
});

it('says nothing to do when the file is complete', function () {
    $cli = envCli()->withApi(api()->on('GET', 'v2/websites/118/env?keys_only=1', fixture('website-118-env-keys.json')));
    $cli->home->write('.env.example', "APP_ENV=\nAPP_KEY=\nDB_CONNECTION=\nDB_HOST=\nDB_PORT=\nMAIL_MAILER=\n");

    $result = $cli->run('configure', 'env');

    expect($result->stdout)->toContain('already has every production key');
});

it('writes nothing under --dry-run', function () {
    $cli = envCli()->withApi(api()->on('GET', 'v2/websites/118/env?keys_only=1', fixture('website-118-env-keys.json')));

    $result = $cli->run('configure', 'env', '--dry-run', '--json');

    expect($result->json()['added'])->toHaveCount(6)
        ->and($cli->home->read('.env.example'))->toBeNull();
});

it('reads another environment with --from', function () {
    $cli = envCli()->withApi(api()->on('GET', 'v2/websites/121/env?keys_only=1', fixture('website-118-env-keys.json')));

    $result = $cli->run('configure', 'env', '--from', 'staging');

    expect($result->exitCode)->toBe(0);
});

it('exits 4 for an environment it does not know', function () {
    $result = envCli()->run('configure', 'env', '--from', 'preview');

    expect($result->exitCode)->toBe(4)
        ->and($result->stderr)->toContain('no preview environment');
});
