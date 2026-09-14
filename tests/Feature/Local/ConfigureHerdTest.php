<?php

declare(strict_types=1);

use Tests\Support\FakeApi;
use Tests\Support\FakeHerd;

function herdApi(): FakeApi
{
    return api()
        ->on('GET', 'v2/websites/118', fixture('website-118.json'))
        ->on('GET', 'v2/servers/61', fixture('server-61.json'))
        ->on('GET', 'v2/websites/118/domains', fixture('website-118-domains.json'));
}

it('writes herd.yml from production and runs herd init', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $herd = new FakeHerd($cli->home->cwd);
    $cli = $cli->withHerd($herd)->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--site', 'marketing', '--yes');

    expect($result->exitCode)->toBe(0);

    $yaml = (string) $cli->home->read('herd.yml');

    expect($yaml)->toContain("php: '8.3'")
        ->and($yaml)->toContain('name: marketing')
        ->and($yaml)->toContain('secured: true')
        ->and($yaml)->toContain('server-id: 402')
        ->and($yaml)->toContain('site-id: 8815')
        ->and($herd->ran)->toContain('init')
        ->and($herd->ran)->toContain('isolate 8.3');
});

it('quotes the PHP version so YAML does not read it as a float', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--print');

    expect($result->stdout)->toContain("php: '8.3'")
        ->and($result->stdout)->not->toContain('php: 8.3'."\n");
});

it('writes a services block only on Herd Pro', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd, pro: true))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--print');

    expect($result->stdout)->toContain('mysql')
        ->and($result->stdout)->toContain("port: '\${DB_PORT}'");
});

it('leaves the services block out with --no-services', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd, pro: true))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--print', '--no-services');

    expect($result->stdout)->not->toContain('mysql');
});

it('keeps keys it does not manage and shows a diff', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli->home->write('herd.yml', "name: marketing\nphp: '8.4'\nexpose:\n  domain: acme\n");
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--yes');

    $yaml = (string) $cli->home->read('herd.yml');

    expect($result->stdout)->toContain('- php: 8.4')
        ->and($result->stdout)->toContain('+ php: 8.3')
        ->and($yaml)->toContain('expose:')
        ->and($yaml)->toContain("php: '8.3'");
});

it('says nothing changed when herd.yml already matches', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd, isolated: '8.3', secured: ['marketing']))->withApi(herdApi());

    $cli->run('configure', 'herd', '--site', 'marketing', '--yes');

    $result = $cli->withApi(herdApi())->run('configure', 'herd', '--site', 'marketing', '--yes');

    expect($result->stdout)->toContain('already matches production');
});

it('exits 2 when Herd is not installed', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd, installed: false))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--yes');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('Herd is not installed');
});

it('needs a confirmation in a pipe', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd))->withApi(herdApi());

    $result = $cli->run('configure', 'herd');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation');
});

it('shows the plan under --dry-run and writes nothing', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--dry-run', '--json');

    expect($result->exitCode)->toBe(0)
        ->and($result->json()['dry_run'])->toBeTrue()
        ->and($result->json()['herd_yml']['php'])->toBe('8.3')
        ->and($cli->home->read('herd.yml'))->toBeNull();
});

it('runs herd directly with --no-yml', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $herd = new FakeHerd($cli->home->cwd);
    $cli = $cli->withHerd($herd)->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--no-yml', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->read('herd.yml'))->toBeNull()
        ->and($herd->ran)->toContain('isolate 8.3')
        ->and($result->stdout)->toContain('Forge ids were not stored');
});

it('still asks before running herd when herd.yml already matches', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $herd = new FakeHerd($cli->home->cwd, isolated: '8.3', secured: ['marketing']);
    $cli = $cli->withHerd($herd)->withApi(herdApi());

    $cli->run('configure', 'herd', '--site', 'marketing', '--yes');
    $herd->ran = [];

    $result = $cli->withApi(herdApi())->run('configure', 'herd', '--site', 'marketing');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation')
        ->and($herd->ran)->toBe([]);
});

it('does not claim to have written herd.yml when it already matched', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd, isolated: '8.3', secured: ['marketing']))->withApi(herdApi());

    $cli->run('configure', 'herd', '--site', 'marketing', '--yes');

    $result = $cli->withApi(herdApi())->run('configure', 'herd', '--site', 'marketing', '--yes', '--json');

    expect($result->json()['wrote'])->toBeFalse();
});

it('shows the file it would create before asking', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->answers(['Write herd.yml and run herd init?' => false]);
    $cli = $cli->withHerd(new FakeHerd($cli->home->cwd))->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--site', 'marketing');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('does not exist yet')
        ->and($result->stdout)->toContain("php: '8.3'")
        ->and($result->stdout)->toContain('herd init')
        ->and($result->stdout)->toContain('Nothing was written')
        ->and($cli->home->read('herd.yml'))->toBeNull();
});

it('relays what herd prints while a step runs on a terminal', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->answers(['Write herd.yml and run herd init?' => true]);
    $herd = new FakeHerd($cli->home->cwd);
    $cli = $cli->withHerd($herd)->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--site', 'marketing');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Applying herd.yml')
        ->and($result->stdout)->toContain('$ herd init -n')
        ->and($result->stdout)->toContain('Installing PHP 8.3')
        ->and($result->stdout)->toContain('Linking marketing.test')
        ->and($herd->ran)->toContain('init');
});

it('runs herd quietly in a pipe', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
    $herd = new FakeHerd($cli->home->cwd);
    $cli = $cli->withHerd($herd)->withApi(herdApi());

    $result = $cli->run('configure', 'herd', '--site', 'marketing', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->not->toContain('Installing PHP')
        ->and($herd->ran)->toContain('init');
});
