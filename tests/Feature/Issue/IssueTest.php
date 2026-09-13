<?php

declare(strict_types=1);
use Tests\Support\CliTester;

const ISSUE = '01J9P7QK3M8T5V2N4B6C8D0E1F';

/** An issue whose fix asks first: the BIMI publisher wants the logo URL. */
const BIMI = '01J9P8QK3M8T5V2N4B6C8D0E2A';

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

it('says what a fix will ask for, and how to answer from a pipe', function () {
    $result = issues()
        ->withApi(api()->on('GET', 'v1/issues/'.BIMI, fixture('issue-01J9P8-bimi.json')))
        ->run('issue', 'view', BIMI);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('asks for Logo URL (SVG Tiny PS), Mark Certificate URL (optional)')
        ->and($result->stdout)->toContain('+ BIMI  default._bimi.acme.dev')
        ->and($result->stdout)->toContain('unolia issue fix 8D0E2A --input logo_url=…');
});

it('asks what the fix needs on a terminal, previews with the answers, then applies them', function () {
    $fixed = fixture('issue-01J9P7-fixed.json');
    $cli = issues()
        ->answers(['Logo URL' => 'https://acme.dev/logo.svg', 'Mark Certificate' => '', 'Apply this fix?' => true])
        ->withApi(api()
            ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json'))
            ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-dry-run.json'))
            ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-applied.json'))
            ->on('POST', 'v1/issues/'.BIMI.'/recheck', $fixed)
            ->on('GET', 'v1/issues/'.BIMI, $fixed));

    $result = $cli->run('issue', 'fix', BIMI);

    $inputs = ['logo_url' => 'https://acme.dev/logo.svg', 'evidence_url' => ''];

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('v=BIMI1; l=https://acme.dev/logo.svg;')
        ->and($result->stdout)->toContain('Logo URL (SVG Tiny PS)  https://acme.dev/logo.svg')
        ->and($cli->api()->calls()[0]['body'])->toBe(['dry_run' => true])
        ->and($cli->api()->calls()[1]['body'])->toBe(['dry_run' => true, 'inputs' => $inputs])
        ->and($cli->api()->calls()[2]['body'])->toBe(['dry_run' => false, 'inputs' => $inputs]);
});

it('takes the answers from --input in a pipe', function () {
    $cli = issues()->withApi(api()
        ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json'))
        ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-dry-run.json'))
        ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-applied.json')));

    $result = $cli->run('issue', 'fix', BIMI, '--yes', '--input', 'logo_url=https://acme.dev/logo.svg');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->calls()[2]['body'])->toBe(['dry_run' => false, 'inputs' => ['logo_url' => 'https://acme.dev/logo.svg']]);
});

it('lets a pipe discover what a fix asks with a dry run', function () {
    $plain = issues()
        ->withApi(api()->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json')))
        ->run('issue', 'fix', BIMI, '--dry-run');

    expect($plain->exitCode)->toBe(0)
        ->and($plain->stdout)->toContain('Asks for      Logo URL (SVG Tiny PS), Mark Certificate URL (optional)')
        ->and($plain->stdout)->toContain('Answer with --input logo_url=…');

    $json = issues()
        ->withApi(api()->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json')))
        ->run('issue', 'fix', BIMI, '--dry-run', '--json');

    expect($json->json()['fixable'])->toBeFalse()
        ->and($json->json()['inputs'][0]['field'])->toBe('logo_url');
});

it('exits 2 when a pipe fixes without the answers, --yes or not', function () {
    $result = issues()
        ->withApi(api()->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json')))
        ->run('issue', 'fix', BIMI, '--yes');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --input')
        ->and($result->stderr)->toContain('logo_url');
});

it('passes on what the fixer refused about an answer', function () {
    $result = issues()
        ->withApi(api()
            ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-asks.json'))
            ->on('POST', 'v1/issues/'.BIMI.'/fix', fixture('issue-fix-bimi-422.json'), 422))
        ->run('issue', 'fix', BIMI, '--yes', '--input', 'logo_url=https://acme.dev/missing.svg');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('The logo cannot be used');
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

it('applies the fix then follows the recheck in a task on a terminal', function () {
    $fixed = fixture('issue-01J9P7-fixed.json');

    $result = issues()
        ->answers(['Apply this fix?' => true])
        ->withApi(api()
            ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-dry-run.json'))
            ->on('POST', 'v1/issues/'.ISSUE.'/fix', fixture('issue-fix-applied.json'))
            ->on('POST', 'v1/issues/'.ISSUE.'/recheck', $fixed)
            ->on('GET', 'v1/issues/'.ISSUE, $fixed))
        ->run('issue', 'fix', ISSUE);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Fix applied')
        ->and($result->stdout)->toContain('Issue fixed');
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
