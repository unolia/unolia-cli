<?php

declare(strict_types=1);

it('watches a deployment by name', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v1/deployments/4812?wait=0', fixture('deployment-4812-running.json'))
            ->on('GET', 'v1/deployments/4812/output?after=0', fixture('deployment-4812-output-0.json'))
            ->on('GET', 'v1/deployments/4812?wait=20', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4812/output?after=1024', fixture('deployment-4812-output-1024.json')))
        ->run('watch', 'deployment', '4812');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('composer install')
        ->and($result->stdout)->toContain('Deployment 4812 success in 52s');
});

it('watches the newest thing this directory started', function () {
    $cli = cli()->withConfig(['team' => 'acme', 'website' => 118]);
    $cli->home->write('.unolia/local.json', (string) json_encode([
        'last_deployment' => 4812,
        'last_deployment_at' => '2026-09-05T09:41:03Z',
    ]));

    $result = $cli
        ->withApi(api()
            ->on('GET', 'v1/deployments/4812?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('watch');

    expect($result->exitCode)->toBe(0);
});

it('falls back to the newest deployment of the linked website', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments.json'))
            ->on('GET', 'v1/websites/118', fixture('website-118.json'))
            ->on('GET', 'v1/repositories/57/actions?per_page=1', ['data' => [], 'meta' => ['last_page' => 1]])
            ->on('GET', 'v1/deployments/4812?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('watch');

    expect($result->exitCode)->toBe(0);
});

it('follows the running deployment of the linked website when no id is given', function () {
    $result = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments-running.json'))
            ->on('GET', 'v1/deployments/4812?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('watch', 'deployment');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deployment 4812 success');
});

it('waits for the next deployment when the latest one has finished', function () {
    $result = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments.json'))
            ->on('GET', 'v1/websites/118', fixture('website-118.json'))
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments.json'))
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments-next.json'))
            ->on('GET', 'v1/deployments/4813?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4813/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('watch', 'deployment');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Waiting for the next deployment of marketing.acme.com')
        ->and($result->stdout)->toContain('Deployment 4812 success');
});

it('replays the latest finished deployment with --last', function () {
    $result = cli()->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withApi(api()
            ->on('GET', 'v1/websites/118/deployments?per_page=1', fixture('deployments.json'))
            ->on('GET', 'v1/deployments/4812?wait=0', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4812/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('deployment', 'watch', '--last');

    expect($result->stderr)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deployment 4812 success');
});

it('exits 4 when there is nothing to watch', function () {
    $result = cli()->run('watch');

    expect($result->exitCode)->toBe(4)
        ->and($result->stderr)->toContain('nothing to watch here yet');
});

it('refuses a kind it does not know', function () {
    $result = cli()->run('watch', 'weather', '1');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('is not something to watch');
});

it('watches a DNS record and exits 6 on timeout', function () {
    $result = cli()
        ->withApi(api()
            ->on('GET', 'v1/records/88231', fixture('record-88231-verified.json')))
        ->run('watch', 'record', '88231');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('is verified');
});
