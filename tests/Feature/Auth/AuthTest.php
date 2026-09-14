<?php

declare(strict_types=1);

use Tests\Support\CliTester;
use Tests\Support\FakeApi;

/** A tester logged in through the device flow, with everything a refresh needs stored. */
function deviceCli(array $scopes = ['project:read', 'deployment:read'], string $host = 'app.unolia.com'): CliTester
{
    $cli = CliTester::make();
    $file = $cli->home->home.'/.config/unolia/hosts.json';
    mkdir(dirname($file), 0700, true);
    file_put_contents($file, (string) json_encode([
        $host => [
            'token' => 'utk_old_1a2b3c4d',
            'kind' => 'user',
            'name' => 'eser',
            'token_name' => 'eser@mac',
            'expires_at' => '2027-09-12T10:00:00Z',
            'scopes' => $scopes,
            'client_id' => '9d2f7c1a-3b4e-4f5a-8c6d-0e1f2a3b4c5d',
            'created_at' => '2026-09-12T10:00:00Z',
        ],
    ], JSON_PRETTY_PRINT));
    chmod($file, 0600);

    return $cli;
}

/** The device flow answers a refresh needs, from discovery to the rename of the new token. */
function refreshApi(string $tokenFixture = 'current-token-scoped.json'): FakeApi
{
    return api()
        ->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json'))
        ->on('POST', 'oauth/device/code', fixture('device-code.json'))
        ->on('POST', 'oauth/token', fixture('oauth-token-second.json'))
        ->on('GET', 'v2/current/token', fixture($tokenFixture))
        ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
        ->on('PATCH', 'v2/current/token', fixture('token-renamed.json'));
}

function storedEntry(CliTester $cli, string $host = 'app.unolia.com'): array
{
    return json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/hosts.json'), true)[$host];
}

describe('auth refresh', function () {
    it('adds scopes to the stored set, revokes the old token and stores the new one', function () {
        $cli = deviceCli()
            ->withApi(refreshApi()->on('DELETE', 'logout', []))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($result->exitCode)->toBe(0)
            ->and($result->stdout)->toContain('Scopes: project:read, deployment:read, deployment:write')
            ->and($result->stdout)->toContain('Refreshed the token for app.unolia.com as eser (user token)');

        $cli->api()->assertEverythingUsed();

        $calls = $cli->api()->calls();
        $authorization = $calls[1];
        $logout = array_values(array_filter($calls, static fn (array $call): bool => $call['method'] === 'DELETE'))[0];
        $rename = array_values(array_filter($calls, static fn (array $call): bool => $call['method'] === 'PATCH'))[0];

        expect($authorization['body']['scope'])->toBe('project:read deployment:read deployment:write')
            ->and($logout['path'])->toBe('logout')
            ->and($rename['body'])->toBe(['name' => 'eser@mac'])
            ->and($cli->api()->lastCall()['method'])->toBe('DELETE');

        $entry = storedEntry($cli);

        expect($entry['token'])->toBe('utk_device_second_1a2b3c4d')
            ->and($entry)->not->toHaveKey('refresh_token')
            ->and($entry['client_id'])->toBe('9d2f7c1a-3b4e-4f5a-8c6d-0e1f2a3b4c5d')
            ->and($entry['token_name'])->toBe('eser@mac')
            ->and($entry['scopes'])->toBe(['project:read', 'deployment:read', 'deployment:write']);
    });

    it('tells the consent page which token it replaces', function () {
        $cli = deviceCli()->withApi(refreshApi()->on('DELETE', 'logout', []))->interactive();
        $file = $cli->home->home.'/.config/unolia/hosts.json';
        $entries = json_decode((string) file_get_contents($file), true);
        $entries['app.unolia.com']['token_id'] = 'tok_previous';
        file_put_contents($file, (string) json_encode($entries));

        $cli->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($cli->browser->opened[0])->toBe('https://app.unolia.com/oauth/device/authorize?user_code=ABCD-EFGH&replaces=tok_previous');
    });

    it('removes scopes from the stored set', function () {
        $cli = deviceCli(['project:read', 'deployment:read', 'env:read'])
            ->withApi(refreshApi()->on('DELETE', 'logout', []))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--remove-scopes', 'env:read');

        expect($result->exitCode)->toBe(0)
            ->and($cli->api()->calls()[1]['body']['scope'])->toBe('project:read deployment:read');
    });

    it('warns about a removed scope that was not there, and refuses to remove everything', function () {
        $cli = deviceCli(['project:read'])
            ->withApi(refreshApi()->on('DELETE', 'logout', []))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'deployment:read', '--remove-scopes', 'env:read');

        expect($result->exitCode)->toBe(0)
            ->and($result->stderr)->toContain('env:read was not among the token scopes');

        $result = deviceCli(['project:read'])
            ->withApi(api()->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json')))
            ->interactive()
            ->run('auth', 'refresh', '--remove-scopes', 'project:read');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('can do nothing');
    });

    it('starts from the default scopes when the stored token is a pasted *, and refuses to remove from it', function () {
        $cli = deviceCli(['*'])->withApi(api()->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json')))->interactive();

        $result = $cli->run('auth', 'refresh', '--remove-scopes', 'env:read');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('every ability');

        $cli = deviceCli(['*'])
            ->withApi(refreshApi()->on('DELETE', 'logout', []))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'project:write');

        expect($result->exitCode)->toBe(0)
            ->and($result->stdout)->toContain('Starting from the default scopes')
            ->and($cli->api()->calls()[1]['body']['scope'])->toBe('provider:read project:read website:read deployment:read domain:read issue:read env:read deploy-script:read project:write');
    });

    it('refuses * with the dashboard hint', function () {
        $cli = deviceCli(['project:read'])->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', '*');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('cannot grant *')
            ->and($result->stderr)->toContain('unolia login --token')
            ->and($cli->api()->calls())->toBe([]);
    });

    it('keeps going with a warning when the old token cannot be revoked', function () {
        $cli = deviceCli()
            ->withApi(refreshApi()->on('DELETE', 'logout', ['message' => 'boom'], 500))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($result->exitCode)->toBe(0)
            ->and($result->stderr)->toContain('could not be revoked')
            ->and(storedEntry($cli)['token'])->toBe('utk_device_second_1a2b3c4d');
    });

    it('exits 2 when nothing is stored and no scopes were given', function () {
        $result = CliTester::make()->interactive()->run('auth', 'refresh');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('not logged in');
    });

    it('signs in fresh when nothing is stored but scopes were given', function () {
        $cli = CliTester::make()->withApi(refreshApi())->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'project:read');

        expect($result->exitCode)->toBe(0)
            ->and($cli->api()->calls()[1]['body']['scope'])->toBe('project:read')
            ->and(array_filter($cli->api()->calls(), static fn (array $call): bool => $call['method'] === 'DELETE'))->toBe([]);
    });

    it('asks the API for the scopes of a token stored without them', function () {
        $cli = cli()
            ->withApi(api()
                ->on('GET', 'v2/current/token', fixture('current-token-team.json'))
                ->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json'))
                ->on('POST', 'oauth/device/code', fixture('device-code.json'))
                ->on('POST', 'oauth/token', fixture('oauth-token-second.json'))
                ->on('GET', 'v2/current/token', fixture('current-token-scoped.json'))
                ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
                ->on('PATCH', 'v2/current/token', fixture('token-renamed.json'))
                ->on('DELETE', 'logout', []))
            ->interactive();

        $result = $cli->run('auth', 'refresh', '--scopes', 'project:read');

        expect($result->exitCode)->toBe(0)
            ->and($cli->api()->calls()[2]['body']['scope'])->toBe('deployment:read deployment:write project:read');
    });

    it('leaves a token from the environment alone', function () {
        $result = deviceCli()->env(['UNOLIA_TOKEN' => 'ci'])->run('auth', 'refresh', '--scopes', 'x');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('comes from UNOLIA_TOKEN');
    });

    it('exits 2 in the pipe face and shows the plan under --dry-run', function () {
        $result = deviceCli()->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($result->exitCode)->toBe(2)
            ->and($result->stderr)->toContain('needs a terminal');

        $result = deviceCli()
            ->withApi(api()->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json')))
            ->run('auth', 'refresh', '--scopes', 'deployment:write', '--dry-run', '--json');

        expect($result->exitCode)->toBe(0)
            ->and($result->json()['scopes'])->toBe(['project:read', 'deployment:read', 'deployment:write'])
            ->and($result->json()['revokes'])->toBeTrue();
    });

    it('passes the refusal and the expiry through with their exit codes', function () {
        $refused = deviceCli()
            ->withApi(api()
                ->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json'))
                ->on('POST', 'oauth/device/code', fixture('device-code.json'))
                ->on('POST', 'oauth/token', fixture('oauth-access-denied.json'), 400))
            ->interactive()
            ->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($refused->exitCode)->toBe(3);

        $expired = deviceCli()
            ->withApi(api()
                ->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json'))
                ->on('POST', 'oauth/device/code', fixture('device-code.json'))
                ->on('POST', 'oauth/token', fixture('oauth-expired.json'), 400))
            ->interactive()
            ->run('auth', 'refresh', '--scopes', 'deployment:write');

        expect($expired->exitCode)->toBe(6)
            ->and(storedEntry(deviceCli())['token'])->toBe('utk_old_1a2b3c4d');
    });
});

describe('auth token', function () {
    it('prints just the token in the pipe face', function () {
        $result = cli()->run('auth', 'token');

        expect($result->exitCode)->toBe(0)
            ->and($result->stdout)->toBe("test-token\n");
    });

    it('prints just the token at a terminal too', function () {
        $result = cli()->interactive()->run('auth', 'token');

        expect($result->stdout)->toBe("test-token\n");
    });

    it('answers with an object in the JSON face', function () {
        $result = cli()->run('auth', 'token', '--json');

        expect($result->json())->toBe(['token' => 'test-token']);
    });

    it('prefers the environment, like every other command', function () {
        $result = cli()->env(['UNOLIA_TOKEN' => 'from-env'])->run('auth', 'token');

        expect($result->stdout)->toBe("from-env\n");
    });

    it('exits 3 when nothing is stored', function () {
        $result = CliTester::make()->run('auth', 'token');

        expect($result->exitCode)->toBe(3)
            ->and($result->stderr)->toContain('not logged in');
    });
});

describe('auth aliases', function () {
    it('reaches logout and status through the auth namespace', function () {
        $cli = cli()->withApi(api()->on('DELETE', 'logout', []));

        expect($cli->run('auth', 'logout')->exitCode)->toBe(0);

        $result = CliTester::make()->run('auth', 'status');

        expect($result->exitCode)->toBe(0)
            ->and($result->stdout)->toContain('not logged in, run unolia login');
    });

    it('lists every auth command under the namespace', function () {
        $result = CliTester::make()->run('auth');

        expect($result->exitCode)->toBe(0)
            ->and($result->stdout)->toContain('login')
            ->and($result->stdout)->toContain('logout')
            ->and($result->stdout)->toContain('status')
            ->and($result->stdout)->toContain('refresh')
            ->and($result->stdout)->toContain('token')
            ->and($result->stdout)->not->toContain('auth:');
    });
});

describe('a rejected token', function () {
    it('is the plain unauthenticated error, with no refresh attempted', function () {
        $cli = deviceCli()->withApi(api()->on('GET', 'v2/teams', fixture('error-401.json'), 401));

        $result = $cli->run('team', 'list');

        expect($result->exitCode)->toBe(3)
            ->and($result->stderr)->toContain('Run unolia login')
            ->and(count($cli->api()->calls()))->toBe(1)
            ->and(storedEntry($cli)['token'])->toBe('utk_old_1a2b3c4d');
    });
});

describe('insufficient scope', function () {
    it('names the command that adds the missing scope', function () {
        $result = cli()
            ->withApi(api()->on('POST', 'v2/websites/118/deployments', fixture('error-403-insufficient-scope.json'), 403))
            ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
            ->run('deploy', '--yes');

        expect($result->exitCode)->toBe(5)
            ->and($result->stderr)->toContain('deployment:write scope')
            ->and($result->stderr)->toContain('Run unolia auth refresh --scopes deployment:write')
            ->and($result->stderr)->not->toContain('--host');
    });

    it('adds --host when the host is not the default', function () {
        $result = CliTester::make()
            ->withToken(host: 'unolia.test')
            ->env(['UNOLIA_HOST' => 'unolia.test'])
            ->withApi(api()->on('GET', 'v2/teams', fixture('error-403-insufficient-scope.json'), 403))
            ->run('team', 'list', '--json');

        expect($result->exitCode)->toBe(5)
            ->and($result->errorJson()['error']['hint'])->toBe('Run unolia auth refresh --scopes deployment:write --host unolia.test');
    });
});
