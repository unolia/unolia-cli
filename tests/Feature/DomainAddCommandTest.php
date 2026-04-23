<?php

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\DomainsRecordsCreate;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('expands @ to the zone root when adding a record', function () {
    config(['settings.api.token' => 'test-token']);

    $mock = MockClient::global([
        DomainsRecordsCreate::class => MockResponse::make(['data' => ['id' => 1]], 201),
    ]);

    $this->artisan('domain:add', [
        'domain' => 'example.com',
        'name' => '@',
        'type' => 'TXT',
        'value' => 'hello',
    ])->assertSuccessful();

    expect($mock->getLastPendingRequest()->body()->all())
        ->toMatchArray([
            'name' => 'example.com',
            'type' => 'TXT',
            'value' => 'hello',
        ]);
});

it('passes a fully qualified name through unchanged', function () {
    config(['settings.api.token' => 'test-token']);

    $mock = MockClient::global([
        DomainsRecordsCreate::class => MockResponse::make(['data' => ['id' => 1]], 201),
    ]);

    $this->artisan('domain:add', [
        'domain' => 'example.com',
        'name' => 'mail.example.com',
        'type' => 'TXT',
        'value' => 'hello',
    ])->assertSuccessful();

    expect($mock->getLastPendingRequest()->body()->all())
        ->toMatchArray(['name' => 'mail.example.com']);
});
