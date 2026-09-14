<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function deployments(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118]);
}

it('lists the deployments of the linked website', function () {
    $cli = deployments()->withApi(api()->on('GET', 'v2/deployments?website=118', fixture('deployments.json')));

    $result = $cli->run('deployment', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('marketing.acme.com')
        ->and($result->stdout)->toContain('52s');
});

it('scopes to the website the git remote maps to when nothing is linked', function () {
    $cli = cli()->withGitRemote()
        ->withApi(api()
            ->on('GET', 'v2/resolve', fixture('resolve-exact.json'))
            ->on('GET', 'v2/deployments?website=118', fixture('deployments.json')));

    $result = $cli->run('deployment', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('4812');
});

it('filters by status and branch', function () {
    $cli = deployments()->withApi(api()->on('GET', 'v2/deployments?status=failed&branch=main', fixture('deployments.json')));

    $cli->run('deployment', 'list', '--status', 'failed', '--branch', 'main');

    expect($cli->api()->lastCall()['query'])->toMatchArray(['status' => 'failed', 'branch' => 'main']);
});

it('shows one deployment', function () {
    $result = deployments()
        ->withApi(api()->on('GET', 'v2/deployments/4812', fixture('deployment-4812-success.json')))
        ->run('deployment', 'view', '4812');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Fix newsletter form');
});

it('prints the log of a deployment', function () {
    $result = deployments()
        ->withApi(api()->on('GET', 'v2/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('deployment', 'logs', '4812');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deployment finished');
});

it('refuses a deployment id that is not a number', function () {
    $result = deployments()->run('deployment', 'logs', 'latest');

    expect($result->exitCode)->toBe(2);
});

it('watches a deployment to the end', function () {
    $cli = deployments()->withApi(api()
        ->on('GET', 'v2/deployments/4812?wait=0', fixture('deployment-4812-running.json'))
        ->on('GET', 'v2/deployments/4812/output?after=0', fixture('deployment-4812-output-0.json'))
        ->on('GET', 'v2/deployments/4812?wait=20', fixture('deployment-4812-success.json'))
        ->on('GET', 'v2/deployments/4812/output?after=1024', fixture('deployment-4812-output-1024.json')));

    $result = $cli->run('deployment', 'watch', '4812');

    expect($result->exitCode)->toBe(0)
        ->and($cli->home->readJson('.unolia/local.json')['last_deployment'])->toBe(4812);
});

it('answers with the final deployment in the JSON face', function () {
    $result = deployments()
        ->withApi(api()
            ->on('GET', 'v2/deployments/4812?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v2/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('deployment', 'watch', '4812', '--json');

    expect($result->json()['status'])->toBe('success');
});

it('says the output is still being fetched instead of printing nothing', function () {
    $syncing = fixture('deployment-4812-output-0.json');
    $syncing['data'] = ['deployment_id' => 4812, 'status' => 'success', 'offset' => 0, 'next_offset' => 0, 'chunk' => '', 'complete' => false, 'syncing' => true];

    $result = deployments()
        ->withApi(api()->on('GET', 'v2/deployments/4812/output?after=0', $syncing))
        ->run('deployment', 'logs', '4812');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('still being fetched');
});
