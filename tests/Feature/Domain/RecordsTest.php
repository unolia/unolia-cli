<?php

declare(strict_types=1);

it('lists the records of a domain', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains/acme.com/records', fixture('records.json')))
        ->run('domain', 'records', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('www.acme.com')
        ->and($result->stdout)->toContain('---');
});

it('filters records by type', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains/acme.com/records', fixture('records.json')))
        ->run('domain', 'records', 'acme.com', '--type', 'MX', '--json');

    expect($result->json())->toHaveCount(1)
        ->and($result->json()[0]['type'])->toBe('MX');
});

it('exits 2 when the domain is missing in a pipe', function () {
    $result = cli()->run('domain', 'records');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing <domain>');
});

it('asks for the domain on a terminal', function () {
    $result = cli()
        ->answers(['Domain name' => 'acme.com'])
        ->withApi(api()->on('GET', 'v1/domains/acme.com/records', fixture('records.json')))
        ->run('domain', 'records');

    expect($result->exitCode)->toBe(0);
});

it('shows one domain', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v1/domains/acme.com', fixture('domain-example-com.json')))
        ->run('domain', 'view', 'acme.com');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('ns1.unolia.com');
});
