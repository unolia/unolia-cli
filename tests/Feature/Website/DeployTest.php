<?php

declare(strict_types=1);
use Tests\Support\CliTester;

function linked(): CliTester
{
    return cli()->withConfig([
        'team' => 'acme',
        'project' => 12,
        'website' => 118,
        'environments' => ['production' => 118, 'staging' => 121],
    ]);
}

it('deploys the linked website with --yes in a pipe', function () {
    $cli = linked()->withApi(api()
        ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201));

    $result = $cli->run('deploy', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deployment 4813 started')
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => false])
        ->and($cli->home->readJson('.unolia/local.json')['last_deployment'])->toBe(4813);
});

it('refuses to deploy in a pipe without --yes', function () {
    $cli = linked()->withApi(api()->on('GET', 'v1/websites/118', fixture('website-118.json')));

    $result = $cli->run('deploy');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation')
        ->and($result->stderr)->toContain('Deploy marketing.acme.com')
        ->and(array_column($cli->api()->calls(), 'method'))->not->toContain('POST');
});

it('exits 3 when nothing is linked and you are not logged in', function () {
    $result = cli()->withoutToken()->withGitRemote()->run('deploy');

    expect($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('not logged in');
});

it('asks before deploying on a terminal, then follows the deployment in a task', function () {
    $cli = linked()
        ->answers(['Deploy marketing.acme.com' => true])
        ->withApi(api()
            ->on('GET', 'v1/websites/118', fixture('website-118.json'))
            ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201)
            ->on('GET', 'v1/deployments/4813?wait=0', fixture('deployment-4812-running.json'))
            ->on('GET', 'v1/deployments/4813/output?after=0', fixture('deployment-4812-output-0.json'))
            ->on('GET', 'v1/deployments/4813?wait=20', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4813/output?after=1024', fixture('deployment-4812-output-1024.json')));

    $result = $cli->run('deploy');

    expect($result->stderr)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deploying marketing.acme.com')
        ->and($result->stdout)->toContain('Deployment 4812 success');
});

it('hands the id back at once with --no-progress on a terminal', function () {
    $cli = linked()
        ->answers(['Deploy marketing.acme.com' => true])
        ->withApi(api()
            ->on('GET', 'v1/websites/118', fixture('website-118.json'))
            ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201));

    $result = $cli->run('deploy', '--no-progress');

    expect($result->stderr)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Deployment 4813 started');
});

it('stops when the confirmation is refused', function () {
    $result = linked()
        ->answers(['Deploy marketing.acme.com' => false])
        ->withApi(api()->on('GET', 'v1/websites/118', fixture('website-118.json')))
        ->run('deploy');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('nothing was deployed');
});

it('previews with --dry-run and changes nothing', function () {
    $cli = linked()->withApi(api()
        ->on('POST', 'v1/websites/118/deployments', fixture('deployment-dry-run.json')));

    $result = $cli->run('deploy', '--dry-run');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => true])
        ->and($result->stdout)->toContain('Would trigger');
});

it('exits 1 when the provider cannot deploy', function () {
    $result = linked()
        ->withApi(api()->on('POST', 'v1/websites/118/deployments', fixture('deployment-dry-run-unsupported.json')))
        ->run('deploy', '--dry-run');

    expect($result->exitCode)->toBe(1);
});

it('deploys an environment by name', function () {
    $cli = linked()->withApi(api()
        ->on('POST', 'v1/websites/121/deployments', fixture('deployment-create-201.json'), 201));

    $result = $cli->run('deploy', 'staging', '--yes');

    expect($result->exitCode)->toBe(0);
});

it('lists the environments it knows when the name is wrong', function () {
    $result = linked()->run('deploy', 'preview');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('no preview environment')
        ->and($result->stderr)->toContain('production, staging');
});

it('waits and streams events with --wait --format ndjson', function () {
    $result = linked()
        ->withApi(api()
            ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201)
            ->on('GET', 'v1/deployments/4813?wait=0', fixture('deployment-4812-running.json'))
            ->on('GET', 'v1/deployments/4813/output?after=0', fixture('deployment-4812-output-0.json'))
            ->on('GET', 'v1/deployments/4813?wait=20', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4813/output?after=1024', fixture('deployment-4812-output-1024.json')))
        ->run('deploy', '--wait', '--yes', '--format', 'ndjson');

    $events = array_column($result->ndjson(), 'event');

    expect($result->exitCode)->toBe(0)
        ->and($events)->toContain('deployment.started')
        ->and($events)->toContain('deployment.output')
        ->and($events)->toContain('deployment.finished');
});

it('exits 1 when the deployment fails', function () {
    $result = linked()
        ->withApi(api()
            ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201)
            ->on('GET', 'v1/deployments/4813?wait=0', fixture('deployment-4812-failed.json'))
            ->on('GET', 'v1/deployments/4813/output?after=0', fixture('deployment-4812-output-1024.json')))
        ->run('deploy', '--wait', '--yes');

    expect($result->exitCode)->toBe(1);
});

it('exits 2 when nothing links this directory', function () {
    $result = cli()->run('deploy');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('not linked to a website');
});

it('is reachable as website deploy and site deploy', function () {
    foreach ([['website', 'deploy', '--yes'], ['site', 'deploy', '--yes']] as $argv) {
        $result = linked()
            ->withApi(api()->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201))
            ->run(...$argv);

        expect($result->exitCode)->toBe(0);
    }
});

it('joins an output line that a byte cursor cut in two', function () {
    $first = fixture('deployment-4812-output-0.json');
    $first['data']['chunk'] = "composer install --no-dev\nphp artisan mig";
    $second = fixture('deployment-4812-output-1024.json');
    $second['data']['chunk'] = "rate --force\nDeployment finished\n";

    $result = linked()
        ->withApi(api()
            ->on('POST', 'v1/websites/118/deployments', fixture('deployment-create-201.json'), 201)
            ->on('GET', 'v1/deployments/4813?wait=0', fixture('deployment-4812-running.json'))
            ->on('GET', 'v1/deployments/4813/output?after=0', $first)
            ->on('GET', 'v1/deployments/4813?wait=20', fixture('deployment-4812-success.json'))
            ->on('GET', 'v1/deployments/4813/output?after=1024', $second))
        ->run('deploy', '--wait', '--yes', '--format', 'ndjson');

    // The stream ends with the final deployment record, which carries no event.
    $output = array_filter($result->ndjson(), static fn (array $line): bool => ($line['event'] ?? null) === 'deployment.output');
    $lines = array_values(array_column($output, 'line'));

    expect($lines)->toBe(['composer install --no-dev', 'php artisan migrate --force', 'Deployment finished']);
});
