<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Out;

function outFor(Format $format, bool $interactive = false, ?string $fields = null): array
{
    $stdout = new BufferedOutput;
    $stderr = new BufferedOutput;
    $face = new Face(interactive: $interactive, color: false, format: $format, fields: $fields);

    return [new Out($face, $stdout, $stderr), $stdout, $stderr];
}

$rows = [
    ['id' => 1, 'domain' => 'acme.com', 'team' => ['name' => 'Acme']],
    ['id' => 2, 'domain' => 'acme.dev', 'team' => ['name' => 'Acme']],
];

it('renders aligned columns when piped', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Table);

    $out->list($rows, ['id' => 'Id', 'domain' => 'Domain']);

    expect($stdout->fetch())->toContain('ID  DOMAIN')->toContain('1   acme.com');
});

it('renders a JSON array', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Json);

    $out->list($rows, ['id' => 'Id', 'domain' => 'Domain']);

    expect(json_decode(trim($stdout->fetch()), true))->toBe($rows);
});

it('renders one JSON object per line', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Ndjson);

    $out->list($rows, ['id' => 'Id']);

    expect(explode("\n", trim($stdout->fetch())))->toHaveCount(2);
});

it('renders CSV with a header row', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Csv);

    $out->list($rows, ['id' => 'Id', 'domain' => 'Domain']);

    expect(trim($stdout->fetch()))->toBe("Id,Domain\n1,acme.com\n2,acme.dev");
});

it('renders YAML', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Yaml);

    $out->list($rows, ['id' => 'Id']);

    expect($stdout->fetch())->toContain('domain: acme.com');
});

it('keeps only the fields that were asked for', function () use ($rows) {
    [$out, $stdout] = outFor(Format::Json, fields: 'domain,team.name');

    $out->list($rows, ['id' => 'Id']);

    expect(json_decode(trim($stdout->fetch()), true)[0])
        ->toBe(['domain' => 'acme.com', 'team' => ['name' => 'Acme']]);
});

it('renders a record as two columns', function () {
    [$out, $stdout] = outFor(Format::Table);

    $out->record(['id' => 118, 'domain' => 'acme.com'], ['id' => 'Id', 'domain' => 'Domain']);

    expect($stdout->fetch())->toContain('Id      118')->toContain('Domain  acme.com');
});

it('writes messages to stdout on the table face and to stderr otherwise', function () {
    [$out, $stdout, $stderr] = outFor(Format::Table);
    $out->info('done');
    expect($stdout->fetch())->toContain('done')->and($stderr->fetch())->toBe('');

    [$out, $stdout, $stderr] = outFor(Format::Json);
    $out->info('done');
    expect($stdout->fetch())->toBe('')->and($stderr->fetch())->toContain('done');
});

it('writes an error envelope on stderr in the JSON face', function () {
    [$out, $stdout, $stderr] = outFor(Format::Json);

    $out->error(CliError::notFound('no such website', 'Run unolia website list'));

    expect($stdout->fetch())->toBe('');

    $envelope = json_decode(trim($stderr->fetch()), true);

    expect($envelope['error']['code'])->toBe('not_found')
        ->and($envelope['error']['exit_code'])->toBe(4)
        ->and($envelope['error']['hint'])->toBe('Run unolia website list');
});

it('prints one line per event in ndjson and nothing in json', function () {
    [$out, $stdout] = outFor(Format::Ndjson);
    $out->event(['event' => 'deployment.output', 'line' => 'composer install'], 'composer install');
    expect(trim($stdout->fetch()))->toBe('{"event":"deployment.output","line":"composer install"}');

    [$out, $stdout] = outFor(Format::Json);
    $out->event(['event' => 'deployment.output'], 'composer install');
    expect($stdout->fetch())->toBe('');
});

it('says nothing for an empty list unless a message is given', function () {
    [$out, $stdout] = outFor(Format::Table);
    $out->list([], ['id' => 'Id']);
    expect($stdout->fetch())->toBe('');

    [$out, $stdout] = outFor(Format::Table);
    $out->list([], ['id' => 'Id'], null, 'Nothing here.');
    expect(trim($stdout->fetch()))->toBe('Nothing here.');

    [$out, $stdout] = outFor(Format::Json);
    $out->list([], ['id' => 'Id'], null, 'Nothing here.');
    expect(trim($stdout->fetch()))->toBe('[]');
});
