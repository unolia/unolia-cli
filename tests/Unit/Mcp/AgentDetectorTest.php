<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Unolia\UnoliaCLI\Mcp\Agent;
use Unolia\UnoliaCLI\Mcp\AgentDetector;
use Unolia\UnoliaCLI\Mcp\Paths;
use Unolia\UnoliaCLI\Mcp\Platform;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/unolia-cli-test-'.getmypid().'-'.uniqid();
    $this->home = $this->dir.'/home';
    $this->cwd = $this->dir.'/project';
    mkdir($this->home, 0755, true);
    mkdir($this->cwd, 0755, true);

    $this->originalHome = $_SERVER['HOME'] ?? null;
    $this->originalXdg = $_SERVER['XDG_CONFIG_HOME'] ?? null;
    $_SERVER['HOME'] = $this->home;
    // An empty value reads as "unset" to Paths and shadows any real
    // XDG_CONFIG_HOME exported by the host (GitHub's ubuntu runners set one).
    $_SERVER['XDG_CONFIG_HOME'] = '';
});

afterEach(function () {
    $_SERVER['HOME'] = $this->originalHome;
    $_SERVER['XDG_CONFIG_HOME'] = $this->originalXdg;
    File::deleteDirectory($this->dir);
});

it('detects an agent from a project directory', function () {
    Process::fake(fn () => Process::result(exitCode: 1));
    mkdir($this->cwd.'/.cursor');

    $detector = new AgentDetector;

    expect($detector->detect(Agent::Cursor, Platform::Linux, $this->cwd))->toBeTrue();
    expect($detector->detect(Agent::Kiro, Platform::Linux, $this->cwd))->toBeFalse();
});

it('detects an agent from a project file', function () {
    Process::fake(fn () => Process::result(exitCode: 1));
    touch($this->cwd.'/CLAUDE.md');

    expect((new AgentDetector)->detect(Agent::Claude, Platform::Linux, $this->cwd))->toBeTrue();
});

it('detects an agent from a home directory path', function () {
    Process::fake(fn () => Process::result(exitCode: 1));
    mkdir($this->home.'/.amp', 0755, true);

    expect((new AgentDetector)->detect(Agent::Amp, Platform::Linux, $this->cwd))->toBeTrue();
});

it('detects an agent from a successful command', function () {
    Process::fake(['command -v claude' => Process::result()]);

    expect((new AgentDetector)->detect(Agent::Claude, Platform::Linux, $this->cwd))->toBeTrue();
});

it('does not detect when nothing matches', function () {
    Process::fake(fn () => Process::result(exitCode: 1));

    expect((new AgentDetector)->detect(Agent::Claude, Platform::Linux, $this->cwd))->toBeFalse();
});

it('detects directories through glob patterns', function () {
    Process::fake(fn () => Process::result(exitCode: 1));
    mkdir($this->home.'/.local/share/JetBrains/Toolbox/apps/PhpStorm/ch-0', 0755, true);

    expect((new AgentDetector)->detect(Agent::Junie, Platform::Linux, $this->cwd))->toBeTrue();
});

it('checks binaries with command -v or where', function () {
    Process::fake([
        'command -v claude' => Process::result(),
        'command -v code' => Process::result(exitCode: 1),
    ]);

    $detector = new AgentDetector;

    expect($detector->binaryExists('claude', Platform::Linux))->toBeTrue();
    expect($detector->binaryExists('code', Platform::Linux))->toBeFalse();
    expect($detector->binaryExists(null, Platform::Linux))->toBeFalse();
});

it('expands XDG_CONFIG_HOME for ~/.config paths', function () {
    $_SERVER['XDG_CONFIG_HOME'] = $this->dir.'/xdg';

    expect(Paths::expand('~/.config/amp/settings.json'))->toBe($this->dir.'/xdg/amp/settings.json');

    $_SERVER['XDG_CONFIG_HOME'] = '';

    expect(Paths::expand('~/.config/amp/settings.json'))->toBe($this->home.'/.config/amp/settings.json');
});

it('expands ~ and resolves relative paths against a base path', function () {
    expect(Paths::expand('~/.cursor/mcp.json'))->toBe($this->home.'/.cursor/mcp.json');
    expect(Paths::expand('.cursor/mcp.json', '/some/project'))->toBe('/some/project/.cursor/mcp.json');
    expect(Paths::expand('/absolute/path', '/some/project'))->toBe('/absolute/path');
});
