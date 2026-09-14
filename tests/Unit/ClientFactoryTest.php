<?php

declare(strict_types=1);

use Tests\Support\TempHome;
use Unolia\Cli\Api\ClientFactory;
use Unolia\Cli\Runtime;

function factoryWith(array $env = []): ClientFactory
{
    $home = new TempHome;

    return new ClientFactory(Runtime::fromEnvironment(array_merge(['HOME' => $home->home], $env), $home->cwd));
}

it('talks https to the real host and checks the certificate', function () {
    $client = factoryWith()->make('app.unolia.com', 'tok');

    expect($client->resolveBaseUrl())->toBe('https://app.unolia.com/api/v2/')
        ->and($client->config()->get('verify'))->toBeTrue();
});

it('keeps https for a local host but skips the certificate check', function () {
    $factory = factoryWith();

    foreach (['unolia.test', 'localhost', 'localhost:8000', 'app.unolia.test'] as $host) {
        expect($factory->make($host, 'tok')->resolveBaseUrl())->toBe('https://'.$host.'/api/v2/', $host)
            ->and($factory->make($host, 'tok')->config()->get('verify'))->toBeFalse($host)
            ->and($factory->auth($host)->resolveBaseUrl())->toBe('https://'.$host.'/api/', $host)
            ->and($factory->auth($host)->config()->get('verify'))->toBeFalse($host)
            ->and($factory->oauth($host)->resolveBaseUrl())->toBe('https://'.$host.'/', $host)
            ->and($factory->oauth($host)->config()->get('verify'))->toBeFalse($host);
    }
});

it('drops to http only when UNOLIA_INSECURE asks for it', function () {
    $factory = factoryWith(['UNOLIA_INSECURE' => '1']);

    expect($factory->make('unolia.test', 'tok')->resolveBaseUrl())->toBe('http://unolia.test/api/v2/')
        ->and($factory->auth('unolia.test')->resolveBaseUrl())->toBe('http://unolia.test/api/')
        ->and($factory->oauth('unolia.test')->resolveBaseUrl())->toBe('http://unolia.test/')
        ->and($factory->make('app.unolia.com', 'tok')->resolveBaseUrl())->toBe('http://app.unolia.com/api/v2/');

    expect(factoryWith(['UNOLIA_INSECURE' => '0'])->make('unolia.test', 'tok')->resolveBaseUrl())->toBe('https://unolia.test/api/v2/');
});

it('reports the same through the runtime', function () {
    $home = new TempHome;

    expect(Runtime::fromEnvironment(['HOME' => $home->home, 'UNOLIA_HOST' => 'unolia.test'], $home->cwd)->insecure())->toBeFalse()
        ->and(Runtime::fromEnvironment(['HOME' => $home->home, 'UNOLIA_INSECURE' => '1'], $home->cwd)->insecure())->toBeTrue();
});
