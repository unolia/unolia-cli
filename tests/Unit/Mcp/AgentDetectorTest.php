<?php

declare(strict_types=1);

use Tests\Support\FakeAgentCli;
use Tests\Support\TempHome;
use Unolia\Cli\Mcp\Agent;
use Unolia\Cli\Mcp\AgentDetector;
use Unolia\Cli\Mcp\ConfigPaths;
use Unolia\Cli\Mcp\Platform;

function detector(TempHome $home, array $binaries = []): AgentDetector
{
    return new AgentDetector(new FakeAgentCli($binaries), new ConfigPaths(['HOME' => $home->home]));
}

it('detects an agent from a project directory or file', function () {
    $home = new TempHome;
    mkdir($home->path('.cursor'));
    touch($home->path('CLAUDE.md'));

    $detector = detector($home);

    expect($detector->detect(Agent::Cursor, Platform::Linux, $home->cwd))->toBeTrue()
        ->and($detector->detect(Agent::Claude, Platform::Linux, $home->cwd))->toBeTrue()
        ->and($detector->detect(Agent::Kiro, Platform::Linux, $home->cwd))->toBeFalse();
});

it('detects an agent from a home directory, through globs too', function () {
    $home = new TempHome;
    mkdir($home->homePath('.amp'));
    mkdir($home->homePath('.local/share/JetBrains/Toolbox/apps/PhpStorm/ch-0'), 0755, true);

    $detector = detector($home);

    expect($detector->detect(Agent::Amp, Platform::Linux, $home->cwd))->toBeTrue()
        ->and($detector->detect(Agent::Junie, Platform::Linux, $home->cwd))->toBeTrue();
});

it('detects an agent from a binary on the PATH', function () {
    $home = new TempHome;

    expect(detector($home, ['codex'])->detected(Platform::Linux, $home->cwd))->toBe([Agent::Codex])
        ->and(detector($home)->detected(Platform::Linux, $home->cwd))->toBe([]);
});

it('expands home, XDG and Windows variables and resolves relative paths', function () {
    $paths = new ConfigPaths(['HOME' => '/home/dev', 'XDG_CONFIG_HOME' => '/xdg', 'APPDATA' => 'C:\Users\dev\AppData\Roaming']);

    expect($paths->expand('~/.config/amp/settings.json'))->toBe('/xdg/amp/settings.json')
        ->and($paths->expand('~/.cursor/mcp.json'))->toBe('/home/dev/.cursor/mcp.json')
        ->and($paths->expand('.cursor/mcp.json', '/work/project'))->toBe('/work/project/.cursor/mcp.json')
        ->and($paths->expand('/absolute/path', '/work/project'))->toBe('/absolute/path')
        ->and($paths->expand('%APPDATA%\Code\User\mcp.json'))->toBe('C:\Users\dev\AppData\Roaming\Code\User\mcp.json')
        ->and((new ConfigPaths(['HOME' => '/home/dev']))->expand('~/.config/amp/settings.json'))->toBe('/home/dev/.config/amp/settings.json');
});

it('shows paths relative to the working directory or the home', function () {
    $paths = new ConfigPaths(['HOME' => '/home/dev']);

    expect($paths->display('/work/project/.cursor/mcp.json', '/work/project'))->toBe('.cursor/mcp.json')
        ->and($paths->display('/home/dev/.cursor/mcp.json', '/work/project'))->toBe('~/.cursor/mcp.json')
        ->and($paths->display('/etc/thing.json', '/work/project'))->toBe('/etc/thing.json');
});
