<?php

declare(strict_types=1);

use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

function mapped(int $status, array $body = [], array $headers = []): CliError
{
    return (new ApiException($status, $body, 'GET', 'v2/websites', $headers))->toCliError();
}

it('maps every documented status to its exit code', function () {
    expect(mapped(401)->exitCode)->toBe(ExitCode::Auth)
        ->and(mapped(403)->exitCode)->toBe(ExitCode::Forbidden)
        ->and(mapped(404)->exitCode)->toBe(ExitCode::NotFound)
        ->and(mapped(422)->exitCode)->toBe(ExitCode::Usage)
        ->and(mapped(429)->exitCode)->toBe(ExitCode::RemoteFailure)
        ->and(mapped(500)->exitCode)->toBe(ExitCode::RemoteFailure)
        ->and(mapped(0)->exitCode)->toBe(ExitCode::RemoteFailure);
});

it('turns a team_required envelope into a usage error with a hint', function () {
    $error = mapped(400, ['error' => ['code' => 'team_required', 'message' => 'Send X-Unolia-Team.']]);

    expect($error->exitCode)->toBe(ExitCode::Usage)
        ->and($error->errorCode)->toBe('team_required')
        ->and($error->hint)->toContain('--team');
});

it('turns a team_not_found envelope into a usage error naming the way out', function () {
    $error = mapped(404, ['error' => ['code' => 'team_not_found', 'message' => 'No team named unolia is reachable with this token.']]);

    expect($error->exitCode)->toBe(ExitCode::Usage)
        ->and($error->errorCode)->toBe('team_not_found')
        ->and($error->getMessage())->toContain('unolia')
        ->and($error->hint)->toContain('unolia teams');
});

it('reads the 402 payload the API already sends', function () {
    $error = mapped(402, [
        'message' => 'Automations need the Studio plan.',
        'error' => 'upgrade_required',
        'feature' => 'Automations',
        'required_plan' => 'Studio',
        'upgrade_url' => 'https://app.unolia.com/acme/billing',
    ]);

    expect($error->exitCode)->toBe(ExitCode::Forbidden)
        ->and($error->getMessage())->toBe('Automations needs the Studio plan')
        ->and($error->hint)->toContain('https://app.unolia.com/acme/billing');
});

it('flattens validation errors into lines', function () {
    $error = mapped(422, ['errors' => ['value' => ['The value must be an IPv4 address.'], 'ttl' => ['Too small.']]]);

    expect($error->getMessage())->toBe("value: The value must be an IPv4 address.\nttl: Too small.")
        ->and($error->details['errors'])->toHaveKey('value');
});

it('never repeats the body of a server error', function () {
    $error = mapped(500, ['message' => 'SQLSTATE[42S02]: table users']);

    expect($error->getMessage())->not->toContain('SQLSTATE')
        ->and($error->getMessage())->toContain('answered 500');
});

it('keeps Retry-After when the API rate limits', function () {
    $error = mapped(429, [], ['Retry-After' => ['30']]);

    expect($error->details['retry_after'])->toBe('30');
});

it('reads a message whether the envelope is new or legacy', function () {
    expect(mapped(404, ['error' => ['message' => 'new shape']])->getMessage())->toBe('new shape')
        ->and(mapped(404, ['message' => 'legacy shape'])->getMessage())->toBe('legacy shape')
        ->and(mapped(404)->getMessage())->toBe('not found');
});

it('keeps the API stable code for a status it does not name itself', function () {
    // The whole point of `error.code` is that a caller can branch on it, so a
    // status without its own arm must not flatten to "request_failed".
    $error = mapped(405, ['error' => ['code' => 'method_not_allowed', 'message' => 'GET or HEAD only.']]);

    expect($error->errorCode)->toBe('method_not_allowed')
        ->and($error->exitCode)->toBe(ExitCode::RemoteFailure)
        ->and($error->getMessage())->toBe('GET or HEAD only.');

    expect(mapped(418)->errorCode)->toBe('request_failed');
});

it('reads the conflict a run action can answer with', function () {
    $error = mapped(409, ['error' => ['code' => 'run_rejected', 'message' => 'Cannot start an archived Automation.']]);

    expect($error->errorCode)->toBe('run_rejected')
        ->and($error->exitCode)->toBe(ExitCode::RemoteFailure)
        ->and($error->getMessage())->toBe('Cannot start an archived Automation.');
});

it('separates a broken request from a resource that cannot do the thing', function () {
    // Both are 422, but only one of them is the caller's fault.
    $unsupported = mapped(422, ['error' => ['code' => 'unsupported', 'message' => 'This issue has no fixer.']]);

    expect($unsupported->errorCode)->toBe('unsupported')
        ->and($unsupported->exitCode)->toBe(ExitCode::RemoteFailure)
        ->and($unsupported->getMessage())->toBe('This issue has no fixer.');

    $invalid = mapped(422, ['error' => ['code' => 'validation_failed'], 'errors' => ['severity' => ['Unknown severity.']]]);

    expect($invalid->exitCode)->toBe(ExitCode::Usage);
});

it('points at auth refresh when the token lacks a scope', function () {
    $body = ['error' => ['code' => 'insufficient_scope', 'message' => 'Needs deployment:write.', 'details' => ['required_scope' => 'deployment:write']]];

    $error = mapped(403, $body);

    expect($error->exitCode)->toBe(ExitCode::Forbidden)
        ->and($error->errorCode)->toBe('forbidden')
        ->and($error->getMessage())->toBe('Needs deployment:write.')
        ->and($error->hint)->toBe('Run unolia auth refresh --scopes deployment:write')
        ->and($error->details['details']['required_scope'])->toBe('deployment:write');

    $elsewhere = (new ApiException(403, $body, 'GET', 'v2/websites', [], '', 'unolia.test'))->toCliError();

    expect($elsewhere->hint)->toBe('Run unolia auth refresh --scopes deployment:write --host unolia.test');

    $plain = mapped(403, ['error' => ['code' => 'forbidden', 'message' => 'Not yours.']]);

    expect($plain->hint)->toContain('abilities of your token');
});

it('says which host it could not reach', function () {
    $error = ApiException::network('unolia.test', 'connection refused')->toCliError();

    expect($error->errorCode)->toBe('network_error')
        ->and($error->getMessage())->toContain('unolia.test');
});
