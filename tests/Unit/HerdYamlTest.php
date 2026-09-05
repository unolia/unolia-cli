<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Unolia\Cli\Local\HerdPlanInput;
use Unolia\Cli\Local\HerdYaml;

it('dumps the PHP version as a quoted string', function () {
    $yaml = HerdYaml::dump(HerdYaml::build(new HerdPlanInput(name: 'marketing', php: '8.3')));

    expect($yaml)->toContain("php: '8.3'")
        ->and(Yaml::parse($yaml)['php'])->toBe('8.3');
});

it('writes the services block from the production database', function () {
    $document = HerdYaml::build(new HerdPlanInput(
        name: 'marketing',
        php: '8.3',
        databaseEngine: 'mysql',
        databaseVersion: '8',
        withServices: true,
    ));

    expect($document['services'])->toBe(['mysql' => ['version' => '8', 'port' => '${DB_PORT}']]);
});

it('maps mariadb onto the mysql service and postgres onto postgresql', function () {
    expect(HerdYaml::databaseService('mariadb'))->toBe('mysql')
        ->and(HerdYaml::databaseService('postgres'))->toBe('postgresql')
        ->and(HerdYaml::databaseService('sqlite'))->toBeNull();
});

it('writes the Forge ids only for a Forge website', function () {
    $forge = HerdYaml::build(new HerdPlanInput(
        name: 'marketing',
        forgeDomain: 'marketing.acme.com',
        forgeServerId: 402,
        forgeSiteId: 8815,
    ));

    expect($forge['integrations']['forge']['marketing.acme.com'])->toBe(['server-id' => 402, 'site-id' => 8815]);

    $other = HerdYaml::build(new HerdPlanInput(name: 'marketing'));

    expect($other)->not->toHaveKey('integrations');
});

it('keeps the keys it does not manage', function () {
    $existing = ['name' => 'old', 'php' => '8.4', 'expose' => ['domain' => 'acme'], 'services' => ['redis' => ['version' => '7']]];
    $desired = HerdYaml::build(new HerdPlanInput(name: 'marketing', php: '8.3', databaseEngine: 'mysql', databaseVersion: '8', withServices: true));

    $merged = HerdYaml::merge($existing, $desired);

    expect($merged['expose'])->toBe(['domain' => 'acme'])
        ->and($merged['php'])->toBe('8.3')
        ->and($merged['name'])->toBe('marketing')
        ->and($merged['services'])->toHaveKeys(['redis', 'mysql']);
});

it('shows the managed keys that changed', function () {
    $existing = ['name' => 'marketing', 'php' => '8.4'];
    $merged = ['name' => 'marketing', 'php' => '8.3'];

    expect(HerdYaml::diff($existing, $merged))->toBe([['-', 'php: 8.4'], ['+', 'php: 8.3']]);
});

it('reads an empty document for a file that is not there', function () {
    expect(HerdYaml::read('/tmp/does-not-exist-'.bin2hex(random_bytes(4)).'.yml'))->toBe([]);
});
