<?php

declare(strict_types=1);

use Tests\Support\CliTester;
use Tests\Support\FakeApi;

function tokenApi(): FakeApi
{
    return api()
        ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
        ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
        ->on('GET', 'v2/teams', fixture('teams.json'))->optional();
}

/** The whole device flow up to the poll, which each test finishes its own way. */
function deviceApi(string $code = 'device-code.json'): FakeApi
{
    return api()
        ->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json'))
        ->on('POST', 'oauth/device/code', fixture($code))
        ->on('GET', 'v2/teams', fixture('teams.json'))->optional();
}

/** The PATCH that names the token; the teams listing follows it, so it is no longer the last call. */
function renameCall(CliTester $cli): array
{
    foreach ($cli->api()->calls() as $call) {
        if ($call['method'] === 'PATCH') {
            return $call;
        }
    }

    return [];
}

function settingsFile(CliTester $cli): array
{
    $path = $cli->home->home.'/.config/unolia/config.json';

    return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?? []) : [];
}

function hostsFile(CliTester $cli): array
{
    return json_decode((string) file_get_contents($cli->home->home.'/.config/unolia/hosts.json'), true);
}

it('logs in with a token option', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $result = $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Logged in to app.unolia.com as eser (user token)');

    $hosts = hostsFile($cli);

    expect($hosts['app.unolia.com']['token'])->toBe('utk_test_9f2c1b7a')
        ->and($hosts['app.unolia.com']['kind'])->toBe('user')
        ->and($hosts['app.unolia.com']['scopes'])->toBe(['*'])
        ->and($hosts['app.unolia.com']['expires_at'])->toBe('2027-09-05T09:00:00Z')
        ->and($hosts['app.unolia.com'])->not->toHaveKey('refresh_token');
});

it('settles on the only team after logging in', function () {
    $cli = CliTester::make()->withApi(api()
        ->on('GET', 'v2/current/token', fixture('current-token-user.json'))
        ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
        ->on('GET', 'v2/teams', ['data' => [['id' => 3, 'slug' => 'acme', 'name' => 'Acme']], 'meta' => ['last_page' => 1]]));

    $result = $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Team: Acme')
        ->and(settingsFile($cli)['default_team'])->toBe('acme');
});

it('asks which team when there are several and none is set', function () {
    $cli = CliTester::make()
        ->withApi(tokenApi())
        ->answers(['Which team' => 'personal']);

    $result = $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Team: Eser')
        ->and(settingsFile($cli)['default_team'])->toBe('personal');
});

it('says how to pick a team in a pipe rather than choosing one', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $result = $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stderr)->toContain('unolia team switch <slug>: acme, personal')
        ->and(settingsFile($cli))->not->toHaveKey('default_team');
});

it('keeps a team the host knows and warns about one from another host', function () {
    $kept = cli()->withApi(tokenApi())->run('login');

    expect($kept->exitCode)->toBe(0)
        ->and($kept->stdout)->toContain('Already logged in as eser');

    $foreign = cli()->withConfig(['team' => 'unolia'])->withApi(tokenApi())->run('login');

    expect($foreign->exitCode)->toBe(0)
        ->and($foreign->stderr)->toContain('This directory names team unolia, which app.unolia.com does not have')
        ->and($foreign->stderr)->toContain('unolia team switch <slug> --local')
        ->and($foreign->stderr)->toContain('acme, personal');
});

it('writes hosts.json with mode 0600', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $cli->run('login', '--token', 'utk_test_9f2c1b7a');

    $mode = fileperms($cli->home->home.'/.config/unolia/hosts.json') & 0777;

    expect(decoct($mode))->toBe('600');
});

it('reads the token from stdin', function () {
    $cli = CliTester::make()
        ->withApi(tokenApi())
        ->stdin("utk_test_9f2c1b7a\n");

    $result = $cli->run('login', '--with-token');

    expect($result->exitCode)->toBe(0)
        ->and(hostsFile($cli)['app.unolia.com']['token'])->toBe('utk_test_9f2c1b7a');
});

it('exits 2 when nothing was piped into --with-token', function () {
    $result = CliTester::make()->run('login', '--with-token');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('nothing was piped');
});

it('exits 2 without a token in the pipe face, and says where a token can come from', function () {
    $result = CliTester::make()->run('login');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --token')
        ->and($result->stderr)->toContain('UNOLIA_TOKEN')
        ->and($result->stderr)->toContain('--with-token');
});

it('exits 3 when the token is rejected', function () {
    $result = CliTester::make()
        ->withApi(api()->on('GET', 'v2/current/token', fixture('error-401.json'), 401))
        ->run('login', '--token', 'nope');

    expect($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('rejected');
});

it('says you are already logged in', function () {
    $result = cli()
        ->withApi(tokenApi())
        ->run('login');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Already logged in as eser')
        ->and($result->stdout)->toContain('Run unolia logout first');
});

it('signs in through the browser with a one time code', function () {
    $cli = CliTester::make()
        ->withApi(deviceApi()
            ->on('POST', 'oauth/token', fixture('oauth-pending.json'), 400)
            ->on('POST', 'oauth/token', fixture('oauth-slow-down.json'), 400)
            ->on('POST', 'oauth/token', fixture('oauth-token.json'))
            ->on('GET', 'v2/current/token', fixture('current-token-device.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('PATCH', 'v2/current/token', fixture('token-renamed.json')))
        ->interactive()->answers(['Which team' => 'acme']);

    $result = $cli->run('login');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('First copy your one-time code: ABCD-EFGH')
        ->and($result->stdout)->toContain('Waiting for you to approve the code')
        ->and($result->stdout)->toContain('Logged in to app.unolia.com as eser (user token)')
        ->and($result->stdout)->toContain('Abilities: provider:read, project:read')
        ->and($cli->browser->opened)->toBe(['https://app.unolia.com/oauth/device?user_code=ABCD-EFGH']);

    $cli->api()->assertEverythingUsed();

    $calls = $cli->api()->calls();
    $authorization = $calls[1];
    $poll = $calls[2];
    $rename = renameCall($cli);

    expect($authorization['body']['client_id'])->toBe('9d2f7c1a-3b4e-4f5a-8c6d-0e1f2a3b4c5d')
        ->and($authorization['body']['scope'])->toBe('provider:read project:read website:read deployment:read domain:read issue:read env:read deploy-script:read')
        ->and($poll['body']['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:device_code')
        ->and($poll['body']['device_code'])->toBe('dc_7f3a9b2c4d5e6f708192a3b4c5d6e7f8')
        ->and($poll['body'])->not->toHaveKey('client_secret')
        ->and($rename['method'])->toBe('PATCH')
        ->and($rename['body']['name'])->toBe((get_current_user() ?: 'cli').'@'.(gethostname() ?: 'localhost'));

    $entry = hostsFile($cli)['app.unolia.com'];

    expect($entry['token'])->toBe('utk_device_5e1a9c3b7d2f')
        ->and($entry)->not->toHaveKey('refresh_token')
        ->and($entry['client_id'])->toBe('9d2f7c1a-3b4e-4f5a-8c6d-0e1f2a3b4c5d')
        ->and($entry['kind'])->toBe('user')
        ->and($entry['name'])->toBe('eser')
        ->and($entry['token_name'])->toBe((get_current_user() ?: 'cli').'@'.(gethostname() ?: 'localhost'))
        ->and($entry['scopes'])->toBe(['provider:read', 'project:read', 'website:read', 'deployment:read', 'domain:read', 'issue:read', 'env:read', 'deploy-script:read'])
        ->and($entry['expires_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and(strtotime($entry['expires_at']))->toBeGreaterThan(time() + 31536000 - 60);
});

it('asks for the scopes given and names the token', function () {
    $cli = CliTester::make()
        ->withApi(deviceApi()
            ->on('POST', 'oauth/token', fixture('oauth-token.json'))
            ->on('GET', 'v2/current/token', fixture('current-token-scoped.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('PATCH', 'v2/current/token', fixture('token-renamed.json')))
        ->interactive()->answers(['Which team' => 'acme']);

    $result = $cli->run('login', '--scopes', 'project:read,deployment:write', '--name', 'laptop');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->calls()[1]['body']['scope'])->toBe('project:read deployment:write')
        ->and(renameCall($cli)['body'])->toBe(['name' => 'laptop'])
        ->and(hostsFile($cli)['app.unolia.com']['token_name'])->toBe('laptop')
        ->and(hostsFile($cli)['app.unolia.com']['scopes'])->toBe(['project:read', 'deployment:read', 'deployment:write']);
});

it('refuses a scope the host does not know', function () {
    $result = CliTester::make()
        ->withApi(api()->on('GET', 'v2/cli/oauth', fixture('cli-oauth.json')))
        ->interactive()
        ->run('login', '--scopes', 'project:read,coffee:brew');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('unknown scope coffee:brew')
        ->and($result->stderr)->toContain('Known scopes: project:read');
});

it('refuses * and points at the dashboard', function () {
    $cli = CliTester::make()->interactive();

    $result = $cli->run('login', '--scopes', 'project:read,*');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('cannot grant *')
        ->and($result->stderr)->toContain('https://app.unolia.com/user/api-tokens/create')
        ->and($result->stderr)->toContain('unolia login --token')
        ->and($cli->api()->calls())->toBe([]);
});

it('exits 1 when the host offers no default scopes', function () {
    $discovery = fixture('cli-oauth.json');
    $discovery['data']['default_scopes'] = [];

    $result = CliTester::make()
        ->withApi(api()->on('GET', 'v2/cli/oauth', $discovery))
        ->interactive()
        ->run('login');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->toContain('offered no default scopes')
        ->and($result->stderr)->toContain('--scopes');
});

it('prints the URL instead of opening a browser with --no-browser', function () {
    $cli = CliTester::make()
        ->withApi(deviceApi()
            ->on('POST', 'oauth/token', fixture('oauth-token.json'))
            ->on('GET', 'v2/current/token', fixture('current-token-device.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('PATCH', 'v2/current/token', fixture('token-renamed.json')))
        ->interactive()->answers(['Which team' => 'acme']);

    $result = $cli->run('login', '--no-browser');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Open https://app.unolia.com/oauth/device and enter the code.')
        ->and($cli->browser->opened)->toBe([]);
});

it('keeps going when the token cannot be renamed', function () {
    $cli = CliTester::make()
        ->withApi(deviceApi()
            ->on('POST', 'oauth/token', fixture('oauth-token.json'))
            ->on('GET', 'v2/current/token', fixture('current-token-device.json'))
            ->on('GET', 'v2/current/authenticated', fixture('current-authenticated-user.json'))
            ->on('PATCH', 'v2/current/token', ['message' => 'boom'], 500))
        ->interactive()->answers(['Which team' => 'acme']);

    $result = $cli->run('login');

    expect($result->exitCode)->toBe(0)
        ->and($result->stderr)->toContain('could not be named')
        ->and(hostsFile($cli)['app.unolia.com']['token_name'])->toBe('cli');
});

it('exits 3 when the login is refused in the browser, whether the server says 401 or 400', function () {
    foreach ([401, 400] as $status) {
        $cli = CliTester::make()
            ->withApi(deviceApi()->on('POST', 'oauth/token', fixture('oauth-access-denied.json'), $status))
            ->interactive();

        $result = $cli->run('login');

        expect($result->exitCode)->toBe(3, (string) $status)
            ->and($result->stderr)->toContain('refused in the browser')
            ->and(is_file($cli->home->home.'/.config/unolia/hosts.json'))->toBeFalse();
    }
});

it('exits 6 when the code expires', function () {
    $result = CliTester::make()
        ->withApi(deviceApi()->on('POST', 'oauth/token', fixture('oauth-expired.json'), 400))
        ->interactive()
        ->run('login');

    expect($result->exitCode)->toBe(6)
        ->and($result->stderr)->toContain('expired')
        ->and($result->stderr)->toContain('Run unolia login again');
});

it('exits 6 when expires_in runs out before an answer', function () {
    $result = CliTester::make()
        ->withApi(deviceApi('device-code-expiring.json')->on('POST', 'oauth/token', fixture('oauth-pending.json'), 400))
        ->interactive()
        ->run('login');

    expect($result->exitCode)->toBe(6)
        ->and($result->stderr)->toContain('expired before it was approved');
});

it('says when the host has no browser login', function () {
    $result = CliTester::make()
        ->withApi(api()->on('GET', 'v2/cli/oauth', fixture('error-404.json'), 404))
        ->interactive()
        ->run('login');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->toContain('does not offer the browser login')
        ->and($result->stderr)->toContain('--with-token');
});

it('answers with a record in the JSON face', function () {
    $result = CliTester::make()
        ->withApi(tokenApi())
        ->run('login', '--token', 'utk_test_9f2c1b7a', '--json');

    expect($result->json()['kind'])->toBe('user')
        ->and($result->json()['name'])->toBe('eser')
        ->and($result->json())->not->toHaveKey('token');
});

it('does not throw the stored token away when the API fails for another reason', function () {
    $result = cli()
        ->withApi(api()->on('GET', 'v2/current/token', ['message' => 'boom'], 500))
        ->run('login');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->not->toContain('fresh login');
});

it('answers to auth login as well', function () {
    $cli = CliTester::make()->withApi(tokenApi());

    $result = $cli->run('auth', 'login', '--token', 'utk_test_9f2c1b7a');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Logged in to app.unolia.com as eser');
});
