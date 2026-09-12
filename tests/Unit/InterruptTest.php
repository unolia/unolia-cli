<?php

declare(strict_types=1);

use Unolia\Cli\Api\Interrupt;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

afterEach(fn () => Interrupt::reset());

it('tells curl to abort once a Ctrl+C is pending, then throws the interruption', function () {
    $progress = Interrupt::progress();

    expect($progress())->toBeFalse();

    Interrupt::flag();

    expect($progress())->toBeTrue();

    try {
        Interrupt::throwIfPending();
        $this->fail('nothing was thrown');
    } catch (CliError $error) {
        expect($error->exitCode)->toBe(ExitCode::Interrupted)
            ->and(Interrupt::pending())->toBeFalse();
    }
});

it('runs a request and clears the in-flight state afterwards', function () {
    expect(Interrupt::during(fn (): string => 'done'))->toBe('done');
});
