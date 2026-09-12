<?php

declare(strict_types=1);
use Tests\Support\CliTester;

const ISSUE = '01J9P7QK3M8T5V2N4B6C8D0E1F';

function issues(): CliTester
{
    return cli()->withConfig(['team' => 'acme', 'project' => 12]);
}

it('lists the open issues of the linked project', function () {
    $cli = issues()->withApi(api()->on('GET', 'v1/issues?project=12', fixture('issues.json')));

    $result = $cli->run('issue', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('8D0E1F')
        ->and($result->stdout)->not->toContain('01J9P7QK3M8T5V2N4B6C8D0E1F')
        ->and($result->stdout)->toContain('Publish a DMARC record');
});

it('is reachable as issues', function () {
    $result = issues()
        ->withApi(api()->on('GET', 'v1/issues?project=12', fixture('issues.json')))
        ->run('issues', '--json');

    expect($result->json())->toHaveCount(1);
});

it('filters to what can be fixed', function () {
    $cli = issues()->withApi(api()->on('GET', 'v1/issues?fixable=1', fixture('issues.json')));

    $cli->run('issue', 'list', '--fixable');

    expect($cli->api()->lastCall()['query'])->toMatchArray(['fixable' => '1']);
});

it('shows one issue from its short id, the tail of the uuid', function () {
    $result = issues()
        ->withApi(api()
            ->on('GET', 'v1/issues?id_suffix=8d0e1f', fixture('issues.json'))
            ->on('GET', 'v1/issues/'.ISSUE, fixture('issue-01J9P7.json')))
        ->run('issue', 'view', '8D0E1F');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('No DMARC record at _dmarc.acme.dev')
        ->and($result->stdout)->toContain('low blast radius · reversible · needs zone:write')
        ->and($result->stdout)->toContain('unolia issue fix 8D0E1F --dry-run');
});

it('previews a fix and changes nothing', function () {
    $cli = issues()->withApi(api()->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-dry-run.json')));

    $result = $cli->run('issue', 'fix', ISSUE, '--dry-run');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('_dmarc.acme.dev')
        ->and($cli->api()->calls())->toHaveCount(1)
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => true]);
});

it('previews then applies with --yes', function () {
    $cli = issues()->withApi(api()
        ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-dry-run.json'))
        ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-applied.json')));

    $result = $cli->run('issue', 'fix', ISSUE, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Fix applied')
        ->and($cli->api()->calls()[0]['body'])->toBe(['dry_run' => true])
        ->and($cli->api()->calls()[1]['body'])->toBe(['dry_run' => false]);
});

it('exits 1 when the fix failed', function () {
    $result = issues()
        ->withApi(api()
            ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-dry-run.json'))
            ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-failed.json')))
        ->run('issue', 'fix', ISSUE, '--yes');

    expect($result->exitCode)->toBe(1);
});

it('exits 2 when a pipe refuses to confirm the fix', function () {
    $result = issues()
        ->withApi(api()->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-dry-run.json')))
        ->run('issue', 'fix', ISSUE);

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation');
});

it('ignores an issue', function () {
    $result = issues()
        ->withApi(api()
            ->on('GET', 'v1/issues/'.ISSUE, fixture('issue-01J9P7.json'))
            ->on('POST', 'v1/issues/'.ISSUE.'/ignore', fixture('issue-01J9P7.json')))
        ->run('issue', 'ignore', ISSUE, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Ignored the issue');
});

it('rechecks an issue and waits', function () {
    $result = issues()
        ->withApi(api()
            ->on('POST', 'v1/issues/'.ISSUE.'/recheck', fixture('issue-01J9P7.json'))
            ->on('GET', 'v1/issues/'.ISSUE, fixture('issue-01J9P7-fixed.json')))
        ->run('issue', 'recheck', ISSUE, '--wait');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('the issue is fixed');
});
