<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Console\Renderers\StyledTableRenderer;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

function sampleTable(): Table
{
    return Table::make(
        Column::make('id', 'Id')->right()->cell(fn (array $row): Cell => Cell::text('#'.$row['id'])->dim()),
        Column::make('status')->cell(fn (array $row): Cell => Status::glyph($row['status'])),
        Column::make('domain', 'Website')->cell(fn (array $row): Cell => Cell::text($row['domain'])->link('https://'.$row['domain'])),
        Column::make('state')->cell(fn (array $row): Cell => Status::word($row['status'])),
    )
        ->sort(fn (array $a, array $b): int => strcmp($a['domain'], $b['domain']))
        ->footer(fn (int $count): string => $count.' websites');
}

function sampleRows(): array
{
    return [
        ['id' => 4, 'domain' => 'zeta.test', 'status' => 'active'],
        ['id' => 135, 'domain' => 'alpha.test', 'status' => 'inactive'],
    ];
}

it('aligns ids right, sorts, indents two spaces and keeps the quiet state silent on a terminal', function () {
    $face = new Face(interactive: true, color: false, format: Format::Table);

    $lines = explode("\n", (new StyledTableRenderer($face))->render(sampleRows(), sampleTable()));

    expect($lines[0])->toBe('    <fg=gray>ID</>     <fg=gray>WEBSITE</>')
        ->and($lines[1])->toBe('  <fg=gray>#135</>  <fg=gray>○</>  alpha.test  <fg=gray>inactive</>')
        ->and($lines[2])->toBe('    <fg=gray>#4</>  <fg=green>●</>  zeta.test')
        ->and($lines[3])->toBe('')
        ->and($lines[4])->toBe('  <fg=gray>2 websites</>');
});

it('wraps links in OSC 8 only when the terminal has colour', function () {
    $face = new Face(interactive: true, color: true, format: Format::Table);

    $output = (new StyledTableRenderer($face))->render(sampleRows(), sampleTable());

    expect($output)->toContain("\e]8;;https://alpha.test\x07alpha.test\e]8;;\x07");
});

it('gives a pipe plain words instead of glyphs and drops empty columns', function () {
    $face = Face::pipe();

    $lines = explode("\n", (new StyledTableRenderer($face))->render(sampleRows(), sampleTable()));

    expect($lines[0])->toBe('  ID  WEBSITE')
        ->and($lines[1])->toBe('#135  alpha.test  inactive')
        ->and($lines[2])->toBe('  #4  zeta.test   active')
        ->and($output = implode("\n", $lines))->not->toContain('●')
        ->and($output)->not->toContain('<fg');
});

it('shortens a time ordered id to its tail, where the random part is', function () {
    expect(Str::shortId('01J9P7QK3M8T5V2N4B6C8D0E1F'))->toBe('8D0E1F')
        ->and(Str::shortId('019f5633-3c4c-70f1-ab88-844537110248'))->toBe('110248')
        ->and(Str::shortId('abc'))->toBe('abc')
        ->and(Str::shortId(null))->toBe('');
});

it('draws a severity as a glyph for the eye and a word for the pipe', function () {
    expect(Status::severity('error')->styled(false))->toBe('<fg=red>✕</>')
        ->and(Status::severity('error')->plainText())->toBe('')
        ->and(Status::severity('warning')->styled(false))->toBe('<fg=yellow>◐</>')
        ->and(Status::severity('info')->styled(false))->toBe('<fg=gray>·</>')
        ->and(Status::severityWord('major')->styled(false))->toBe('<fg=red>major</>')
        ->and(Status::severityWord('minor')->plainText())->toBe('minor');
});

it('drops the box: a plain list is drawn by the same renderer with the id on the right', function () {
    $face = new Face(interactive: false, color: false, format: Format::Table);
    $out = new Out($face, $stdout = new BufferedOutput, new BufferedOutput);

    $out->list([['id' => 7, 'name' => 'Seven'], ['id' => 12, 'name' => 'Twelve']], ['id' => 'Id', 'name' => 'Name']);

    expect($stdout->fetch())->toBe("ID  NAME\n 7  Seven\n12  Twelve\n");
});

it('prints a schedule in the local zone and adds the schedule zone when it differs', function () {
    $previous = getenv('TZ');
    putenv('TZ=Europe/Paris');

    try {
        expect(RelativeTime::at('2026-09-14T09:00:00Z', 'UTC'))->toBe('Mon 14 Sep 2026, 11:00 Europe/Paris (09:00 UTC)')
            ->and(RelativeTime::at('2026-09-14T09:00:00Z', 'Europe/Paris'))->toBe('Mon 14 Sep 2026, 11:00 Europe/Paris')
            ->and(RelativeTime::at(null, 'UTC', '-'))->toBe('-');
    } finally {
        putenv($previous === false ? 'TZ' : 'TZ='.$previous);
    }
});
