<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\Jq;

it('tells you how to get jq when it is not installed', function () {
    $jq = new Jq('/nowhere/jq-does-not-exist');

    expect(fn () => $jq->filter('{}', '.'))
        ->toThrow(CliError::class);
});

it('says jq is missing rather than crashing', function () {
    $finder = new ExecutableFinder;

    if ($finder->find('jq') !== null) {
        expect((new Jq)->filter('{"data":[{"domain":"acme.com"}]}', '.data[0].domain'))
            ->toContain('acme.com');
    }

    expect(fn () => (new Jq('/bin/false'))->filter('{}', '.'))
        ->toThrow(CliError::class, 'jq failed');
});

it('filters the JSON a command printed', function () {
    if ((new ExecutableFinder)->find('jq') === null) {
        expect(true)->toBeTrue();

        return;
    }

    $result = cli()
        ->withApi(api()->on('GET', 'v2/domains', fixture('domains.json')))
        ->run('domain', 'list', '--json', '--jq', '.[0].domain');

    expect($result->exitCode)->toBe(0)
        ->and(trim($result->stdout))->toBe('"acme.com"');
});
