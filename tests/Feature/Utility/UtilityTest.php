<?php

declare(strict_types=1);

use Tests\Support\CliTester;

it('sets, reads and lists options', function () {
    $cli = CliTester::make();

    expect($cli->run('config', 'set', 'format', 'json')->exitCode)->toBe(0);

    $get = $cli->run('config', 'get', 'format');

    expect(trim($get->stdout))->toBe('json');

    $list = $cli->run('config', 'list');

    expect($list->stdout)->toContain('default_team')->toContain('format');
});

it('forgets an option when no value is given', function () {
    $cli = CliTester::make();
    $cli->run('config', 'set', 'default_team', 'acme');

    $cli->run('config', 'set', 'default_team');

    expect(trim($cli->run('config', 'get', 'default_team')->stdout))->toBe('');
});

it('lists the known keys for an unknown one', function () {
    $result = CliTester::make()->run('config', 'get', 'colour');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('unknown config key colour')
        ->and($result->stderr)->toContain('default_team');
});

it('explains how to upgrade a Composer install', function () {
    $result = CliTester::make()->run('upgrade', '--check');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('composer global update unolia/unolia-cli');
});

it('answers with the channel in the JSON face', function () {
    $result = CliTester::make()->run('upgrade', '--json');

    expect($result->json()['channel'])->toBe('composer');
});
