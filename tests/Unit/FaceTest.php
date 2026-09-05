<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;

function detect(array $argv, array $env = []): Face
{
    return Face::detect(new ArgvInput(['unolia', ...$argv]), new BufferedOutput, $env);
}

it('is never interactive without a terminal', function () {
    expect(detect(['domain', 'list'])->interactive)->toBeFalse();
});

it('reads the format from --format, then --json, then the environment', function () {
    expect(detect(['domain', 'list'])->format)->toBe(Format::Table)
        ->and(detect(['domain', 'list', '--json'])->format)->toBe(Format::Json)
        ->and(detect(['domain', 'list', '--format', 'ndjson'])->format)->toBe(Format::Ndjson)
        ->and(detect(['domain', 'list', '--json', '--format', 'csv'])->format)->toBe(Format::Csv)
        ->and(detect(['domain', 'list'], ['UNOLIA_FORMAT' => 'yaml'])->format)->toBe(Format::Yaml);
});

it('reads the fields of --json', function () {
    expect(detect(['domain', 'list', '--json=id,domain'])->fields)->toBe('id,domain')
        ->and(detect(['domain', 'list', '--json', 'id,domain'])->fields)->toBe('id,domain')
        ->and(detect(['domain', 'list', '--json'])->fields)->toBeNull();
});

it('does not swallow the next flag as the value of --json', function () {
    $face = detect(['domain', 'list', '--json', '--jq', '.[0].domain']);

    expect($face->fields)->toBeNull()
        ->and($face->jq)->toBe('.[0].domain');
});

it('reads the flags that change what a command may do', function () {
    $face = detect(['website', 'deploy', '--yes', '--dry-run', '--paginate', '--jq', '.data']);

    expect($face->yes)->toBeTrue()
        ->and($face->dryRun)->toBeTrue()
        ->and($face->paginate)->toBeTrue()
        ->and($face->jq)->toBe('.data');
});

it('refuses a format it does not know', function () {
    expect(fn () => detect(['domain', 'list', '--format', 'xml']))
        ->toThrow(CliError::class, 'unknown format xml');
});

it('knows which formats are for machines', function () {
    expect(Format::Table->isStructured())->toBeFalse()
        ->and(Format::Json->isStructured())->toBeTrue()
        ->and(Format::Ndjson->isStructured())->toBeTrue();
});
