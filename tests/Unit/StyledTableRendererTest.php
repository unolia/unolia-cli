<?php

declare(strict_types=1);

use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Renderers\StyledTableRenderer;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Column;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Table;

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

it('aligns ids right, sorts, and keeps the quiet state silent on a terminal', function () {
    $face = new Face(interactive: true, color: false, format: Format::Table);

    $lines = explode("\n", (new StyledTableRenderer($face))->render(sampleRows(), sampleTable()));

    expect($lines[0])->toBe('  <fg=gray>ID</>     <fg=gray>WEBSITE</>')
        ->and($lines[1])->toBe('<fg=gray>#135</>  <fg=gray>○</>  alpha.test  <fg=gray>inactive</>')
        ->and($lines[2])->toBe('  <fg=gray>#4</>  <fg=green>●</>  zeta.test')
        ->and($lines[4])->toBe('<fg=gray>2 websites</>');
});

it('wraps links in OSC 8 only when the terminal has colour', function () {
    $face = new Face(interactive: true, color: true, format: Format::Table);

    $output = (new StyledTableRenderer($face))->render(sampleRows(), sampleTable());

    expect($output)->toContain("\e]8;;https://alpha.test\e\\alpha.test\e]8;;\e\\");
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
