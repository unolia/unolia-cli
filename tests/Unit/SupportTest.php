<?php

declare(strict_types=1);

use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ErrorRenderer;
use Unolia\Cli\Console\FieldSelector;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Duration;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

it('parses durations', function () {
    expect(Duration::parse('90', 0))->toBe(90)
        ->and(Duration::parse('90s', 0))->toBe(90)
        ->and(Duration::parse('10m', 0))->toBe(600)
        ->and(Duration::parse('2h', 0))->toBe(7200)
        ->and(Duration::parse(null, 15))->toBe(15);
});

it('refuses a duration it cannot read', function () {
    expect(fn () => Duration::parse('soon', 0))->toThrow(CliError::class, 'is not a duration');
});

it('writes durations the way a person reads them', function () {
    expect(RelativeTime::duration(52))->toBe('52s')
        ->and(RelativeTime::duration(250))->toBe('4m 10s')
        ->and(RelativeTime::duration(3600))->toBe('1h')
        ->and(RelativeTime::duration(90000))->toBe('1d 1h')
        ->and(RelativeTime::duration(null))->toBe('-');
});

it('says how long ago something happened', function () {
    $now = new DateTimeImmutable('2026-09-05T10:00:00Z');

    expect(RelativeTime::ago('2026-09-05T09:00:00Z', $now))->toBe('1h ago')
        ->and(RelativeTime::ago('2026-09-05T11:00:00Z', $now))->toBe('1h from now')
        ->and(RelativeTime::ago(null, $now))->toBe('never');
});

it('reads and writes dot paths', function () {
    $row = ['team' => ['name' => 'Acme'], 'id' => 3];

    expect(Arr::get($row, 'team.name'))->toBe('Acme')
        ->and(Arr::get($row, 'team.slug', 'none'))->toBe('none')
        ->and(Arr::has($row, 'team.name'))->toBeTrue()
        ->and(Arr::set([], 'a.b.c', 1))->toBe(['a' => ['b' => ['c' => 1]]]);
});

it('keeps the shape when it selects fields', function () {
    $rows = [['id' => 1, 'team' => ['name' => 'Acme', 'id' => 3]]];

    expect(FieldSelector::apply($rows, 'id,team.name'))
        ->toBe([['id' => 1, 'team' => ['name' => 'Acme']]]);

    expect(FieldSelector::apply(['id' => 1, 'domain' => 'acme.com'], 'domain'))
        ->toBe(['domain' => 'acme.com']);

    expect(FieldSelector::apply($rows, null))->toBe($rows);
});

it('presents values without surprises', function () {
    expect(Str::scalar(null))->toBe('-')
        ->and(Str::scalar(true))->toBe('yes')
        ->and(Str::scalar(false))->toBe('no')
        ->and(Str::scalar(['a', 'b']))->toBe('a, b')
        ->and(Str::limit('a very long value indeed', 10))->toBe('a very lon…')
        ->and(Str::stripAnsi("\e[32mgreen\e[0m"))->toBe('green');
});

it('renders a verbose trace without the arguments of the calls', function () {
    $throwable = (static fn (string $secret): RuntimeException => new RuntimeException('boom'))('bearer-secret-value');

    $trace = ErrorRenderer::trace($throwable);

    expect($trace)->toContain('{main}')
        ->and($trace)->not->toContain('bearer-secret-value')
        ->and($trace)->not->toContain('secret');
});
