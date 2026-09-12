<?php

declare(strict_types=1);

use Tests\Support\TempHome;
use Unolia\Cli\Config\Hosts;
use Unolia\Cli\Config\Paths;
use Unolia\Cli\Config\Settings;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Context\ProjectConfig;

it('puts the configuration under XDG_CONFIG_HOME when it is set', function () {
    $home = new TempHome;

    $paths = new Paths(['HOME' => $home->home]);
    expect($paths->configDir())->toBe($home->home.'/.config/unolia');

    $paths = new Paths(['HOME' => $home->home, 'XDG_CONFIG_HOME' => $home->root.'/xdg']);
    expect($paths->configDir())->toBe($home->root.'/xdg/unolia');
});

it('writes hosts.json with mode 0600 and config.json with 0644', function () {
    $home = new TempHome;
    $paths = new Paths(['HOME' => $home->home]);

    $hosts = new Hosts($paths, ['HOME' => $home->home]);
    $hosts->put('app.unolia.com', 'secret', 'user', 'eser');

    $settings = new Settings($paths, ['HOME' => $home->home]);
    $settings->set('default_team', 'acme');

    expect(decoct(fileperms($paths->hostsFile()) & 0777))->toBe('600')
        ->and(decoct(fileperms($paths->settingsFile()) & 0777))->toBe('644')
        ->and($hosts->tokenFor('app.unolia.com'))->toBe('secret');
});

it('stores what the device flow learned', function () {
    $home = new TempHome;
    $paths = new Paths(['HOME' => $home->home]);
    $hosts = new Hosts($paths, ['HOME' => $home->home]);

    $hosts->putEntry('app.unolia.com', [
        'token' => 'access',
        'kind' => 'user',
        'name' => 'eser',
        'token_name' => 'eser@mac',
        'expires_at' => '2027-09-12T10:00:00+00:00',
        'scopes' => ['project:read'],
        'client_id' => 'client',
    ]);

    expect($hosts->tokenFor('app.unolia.com'))->toBe('access')
        ->and($hosts->clientId('app.unolia.com'))->toBe('client')
        ->and($hosts->scopes('app.unolia.com'))->toBe(['project:read'])
        ->and($hosts->expiresAt('app.unolia.com'))->toBe('2027-09-12T10:00:00+00:00')
        ->and($hosts->tokenName('app.unolia.com'))->toBe('eser@mac')
        ->and(decoct(fileperms($paths->hostsFile()) & 0777))->toBe('600');

    $plain = new Hosts($paths, ['HOME' => $home->home]);
    $plain->put('unolia.test', 'pasted', 'team', 'bot');

    expect($plain->scopes('unolia.test'))->toBeNull()
        ->and($plain->clientId('unolia.test'))->toBeNull();
});

it('prefers the environment over the file, and warns about the old variable', function () {
    $home = new TempHome;
    $paths = new Paths(['HOME' => $home->home]);

    (new Hosts($paths, ['HOME' => $home->home]))->put('app.unolia.com', 'from-file');

    $env = new Hosts($paths, ['HOME' => $home->home, 'UNOLIA_TOKEN' => 'from-env']);
    expect($env->tokenFor('app.unolia.com'))->toBe('from-env');

    $deprecated = new Hosts($paths, ['HOME' => $home->home, 'UNOLIA_API_TOKEN' => 'old']);
    expect($deprecated->tokenFor('app.unolia.com'))->toBe('old')
        ->and($deprecated->takeNotices())->toBe(['UNOLIA_API_TOKEN is deprecated. Use UNOLIA_TOKEN.']);
});

it('migrates the v1 token once and leaves the old file alone', function () {
    $home = new TempHome;
    $legacy = $home->home.'/.unolia/cli';
    mkdir($legacy, 0755, true);
    file_put_contents($legacy.'/config.json', (string) json_encode(['api' => ['token' => 'v1-token']]));

    $paths = new Paths(['HOME' => $home->home]);
    $hosts = new Hosts($paths, ['HOME' => $home->home]);

    expect($hosts->tokenFor('app.unolia.com'))->toBe('v1-token')
        ->and($hosts->takeNotices())->toBe(['Migrated your token from ~/.unolia/cli/config.json'])
        ->and(is_file($legacy.'/config.json'))->toBeTrue()
        ->and(is_file($paths->hostsFile()))->toBeTrue();
});

it('takes the host from UNOLIA_HOST and strips the scheme', function () {
    $home = new TempHome;
    $paths = new Paths(['HOME' => $home->home]);

    expect((new Settings($paths, ['HOME' => $home->home]))->host())->toBe('app.unolia.com')
        ->and((new Settings($paths, ['HOME' => $home->home, 'UNOLIA_HOST' => 'https://unolia.test/']))->host())->toBe('unolia.test');
});

it('refuses an unknown settings key', function () {
    $home = new TempHome;
    $settings = new Settings(new Paths(['HOME' => $home->home]), ['HOME' => $home->home]);

    expect(fn () => $settings->get('nope'))->toThrow(CliError::class, 'unknown config key nope');
});

it('finds .unolia/config.json by walking up to the git root', function () {
    $home = new TempHome;
    mkdir($home->cwd.'/.unolia', 0755, true);
    file_put_contents($home->cwd.'/.unolia/config.json', (string) json_encode([
        'team' => 'acme', 'project' => 12, 'website' => 118, 'environments' => ['staging' => 121],
    ]));
    mkdir($home->cwd.'/app/Http', 0755, true);

    $config = ProjectConfig::discover($home->cwd.'/app/Http', $home->cwd);

    expect($config->exists())->toBeTrue()
        ->and($config->team())->toBe('acme')
        ->and($config->project())->toBe(12)
        ->and($config->website())->toBe(118)
        ->and($config->environments())->toBe(['staging' => 121])
        ->and($config->rootDir())->toBe($home->cwd);
});

it('says which file is broken', function () {
    $home = new TempHome;
    mkdir($home->cwd.'/.unolia', 0755, true);
    file_put_contents($home->cwd.'/.unolia/config.json', '{not json');

    expect(fn () => ProjectConfig::discover($home->cwd, $home->cwd))
        ->toThrow(CliError::class, 'is not valid JSON');
});
