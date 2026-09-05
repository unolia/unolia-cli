<?php

declare(strict_types=1);

use Tests\Support\CliTester;
use Tests\Support\FakeApi;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/** A tester that is already logged in, which is what most commands need. */
function cli(): CliTester
{
    return CliTester::make()->withToken();
}

function api(): FakeApi
{
    return FakeApi::make();
}

/**
 * @return array<mixed>
 */
function fixture(string $name): array
{
    return FakeApi::fixture($name);
}
