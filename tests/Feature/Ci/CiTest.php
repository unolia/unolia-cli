<?php

declare(strict_types=1);
use Tests\Support\CliTester;
use Tests\Support\FakeApi;

function repo(): CliTester
{
    return cli()
        ->withConfig(['team' => 'acme', 'project' => 12, 'website' => 118])
        ->withGitRemote();
}

function withRepository(FakeApi $api): FakeApi
{
    return $api->on('GET', 'v1/websites/118', fixture('website-118.json'));
}

it('lists the runs of the current branch', function () {
    $cli = repo()->withApi(withRepository(api())
        ->on('GET', 'v1/repositories/57/actions?branch=main', fixture('actions-branch-main.json')));

    $result = $cli->run('ci', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('#1187')
        ->and($result->stdout)->toContain('tests');
});

it('is reachable as bare ci', function () {
    $result = repo()
        ->withApi(withRepository(api())
            ->on('GET', 'v1/repositories/57/actions?branch=main', fixture('actions-branch-main.json')))
        ->run('ci');

    expect($result->exitCode)->toBe(0);
});

it('drops the branch filter with --all-branches', function () {
    $cli = repo()->withApi(withRepository(api())
        ->on('GET', 'v1/repositories/57/actions', fixture('actions-branch-main.json')));

    $cli->run('ci', 'list', '--all-branches');

    expect($cli->api()->lastCall()['query'])->not->toHaveKey('branch');
});

it('takes a repository by full name', function () {
    $cli = repo()->withApi(api()
        ->on('GET', 'v1/repositories?q=acme/marketing', fixture('repositories.json'))
        ->on('GET', 'v1/repositories/57/actions?branch=main', fixture('actions-branch-main.json')));

    $result = $cli->run('ci', 'list', '--repo', 'acme/marketing');

    expect($result->exitCode)->toBe(0);
});

it('shows one run and its jobs', function () {
    $result = repo()
        ->withApi(api()->on('GET', 'v1/actions/9021', fixture('action-1187-completed.json')))
        ->run('ci', 'view', '9021');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Run         #1187')
        ->and($result->stdout)->toContain('lint');
});

it('accepts a run number', function () {
    $result = repo()
        ->withApi(withRepository(api())
            ->on('GET', 'v1/repositories/57/actions?run_number=1187', fixture('actions-branch-main.json'))
            ->on('GET', 'v1/actions/9021', fixture('action-1187-completed.json')))
        ->run('ci', 'view', '#1187');

    expect($result->exitCode)->toBe(0);
});

it('watches a run to the end', function () {
    $cli = repo()->withApi(api()
        ->on('GET', 'v1/actions/9022?wait=0', fixture('action-1187-in-progress.json'))
        ->on('GET', 'v1/actions/9022?wait=20', fixture('action-1187-completed.json')));

    $result = $cli->run('ci', 'watch', '9021');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Run #1187 success')
        ->and($cli->home->readJson('.unolia/local.json')['last_ci_run'])->toBe(9021);
});

it('exits 1 when the run failed', function () {
    $result = repo()
        ->withApi(api()->on('GET', 'v1/actions/9021?wait=0', fixture('action-1187-failed.json')))
        ->run('ci', 'watch', '9021');

    expect($result->exitCode)->toBe(1);
});

it('streams jobs as events', function () {
    $result = repo()
        ->withApi(api()
            ->on('GET', 'v1/actions/9022?wait=0', fixture('action-1187-in-progress.json'))
            ->on('GET', 'v1/actions/9021?wait=20', fixture('action-1187-completed.json')))
        ->run('ci', 'watch', '9021', '--format', 'ndjson');

    $events = array_column($result->ndjson(), 'event');

    expect($events)->toContain('run.updated')
        ->and($events)->toContain('job.completed')
        ->and($events)->toContain('run.completed');
});

it('prints the log of a job', function () {
    $result = repo()
        ->withApi(api()
            ->on('GET', 'v1/actions/9021', fixture('action-1187-completed.json'))
            ->on('GET', 'v1/actions/9021/jobs/3311/log?after=0', fixture('action-1187-job-3311-log.json')))
        ->run('ci', 'logs', '9021', '--job', 'lint');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('All files pass');
});

it('re-runs the failed jobs after asking', function () {
    $cli = repo()
        ->answers(['Re-run the failed jobs' => true])
        ->withApi(api()->on('POST', 'v1/actions/9021/rerun', fixture('action-rerun-202.json'), 202));

    $result = $cli->run('ci', 'rerun', '9021', '--failed', '--no-progress');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Re-running #1187 · unolia ci watch')
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => false, 'failed_only' => true]);
});

it('follows the new attempt in a task on a terminal', function () {
    $cli = repo()
        ->answers(['Re-run every job' => true])
        ->withApi(api()
            ->on('POST', 'v1/actions/9021/rerun', fixture('action-rerun-202.json'), 202)
            ->on('GET', 'v1/actions/9022?wait=0', fixture('action-1187-in-progress.json'))
            ->on('GET', 'v1/actions/9022?wait=20', fixture('action-1187-completed.json')));

    $result = $cli->run('ci', 'rerun', '9021');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Re-running #1187')
        ->and($result->stdout)->toContain('Run #1187');
});

it('previews a rerun', function () {
    $result = repo()
        ->withApi(api()->on('POST', 'v1/actions/9021/rerun', fixture('action-rerun-dry-run.json')))
        ->run('ci', 'rerun', '9021', '--dry-run', '--json');

    expect($result->json()['would_send'])->toBe('WorkflowRunRerunFailedJobs');
});

it('cancels a run with --yes', function () {
    $result = repo()
        ->withApi(api()->on('POST', 'v1/actions/9021/cancel', fixture('action-rerun-202.json')))
        ->run('ci', 'cancel', '9021', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Cancelled #1187');
});

it('lists repositories', function () {
    $result = cli()
        ->withConfig(['team' => 'acme', 'project' => 12])
        ->withApi(api()->on('GET', 'v1/repositories?project=12', fixture('repositories.json')))
        ->run('repo', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('acme/marketing');
});

it('shows the repository of this directory', function () {
    $result = repo()
        ->withApi(withRepository(api())->on('GET', 'v1/repositories/57', fixture('repository-57.json')))
        ->run('repo', 'view');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Default branch  main');
});

it('refuses to re-run in a pipe without --yes', function () {
    $result = repo()->run('ci', 'rerun', '9021');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation');
});

it('re-runs with --yes in a pipe', function () {
    $cli = repo()->withApi(api()->on('POST', 'v1/actions/9021/rerun', fixture('action-rerun-202.json'), 202));

    $result = $cli->run('ci', 'rerun', '9021', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => false, 'failed_only' => false]);
});

it('says a job log is still being fetched instead of printing nothing', function () {
    $syncing = fixture('action-1187-job-3311-log.json');
    $syncing['data'] = ['job_id' => 3311, 'log_state' => 'fetching', 'offset' => 0, 'next_offset' => 0, 'chunk' => '', 'complete' => false, 'syncing' => true];

    $result = repo()
        ->withApi(api()
            ->on('GET', 'v1/actions/9021', fixture('action-1187-completed.json'))
            ->on('GET', 'v1/actions/9021/jobs/3311/log?after=0', $syncing))
        ->run('ci', 'logs', '9021', '--job', 'lint');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('still being fetched');
});
