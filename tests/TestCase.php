<?php

declare(strict_types=1);

namespace Tests;

use Laravel\Prompts\Prompt;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\TempHome;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        TempHome::destroyAll();

        Prompt::interactive(false);

        parent::tearDown();
    }
}
