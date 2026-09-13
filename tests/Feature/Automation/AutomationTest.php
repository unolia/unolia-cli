<?php

declare(strict_types=1);
use Tests\Support\CliTester;

const RUN = '01J9A2K7Q4X0N1R8S3T5V6W7Y8';

function automations(): CliTester
{
    return cli()->withConfig(['team' => 'acme']);
}

it('lists automations', function () {
    $result = automations()
        ->withApi(api()->on('GET', 'v1/automations', fixture('automations.json')))
        ->run('automation', 'list');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Update Ubuntu servers')
        ->and($result->stdout)->toContain('manual, every Monday at 4:00am')
        ->and($result->stdout)->toMatch('/Tue 8 Sep 2026, \d\d:\d\d [A-Za-z_\/]+/');
});

it('shows one automation with its last runs', function () {
    $result = automations()
        ->withApi(api()
            ->on('GET', 'v1/automations/7', fixture('automation-7.json'))
            ->on('GET', 'v1/automation-runs?automation=7', fixture('automation-runs.json')))
        ->run('automation', 'view', '7');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Update Ubuntu servers')
        ->and($result->stdout)->toContain('01J9A2K7Q4');
});

it('finds an automation by name', function () {
    $result = automations()
        ->withApi(api()
            ->on('GET', 'v1/automations?q=Update Ubuntu servers', fixture('automations.json'))
            ->on('GET', 'v1/automations/7', fixture('automation-7.json'))
            ->on('GET', 'v1/automation-runs?automation=7', fixture('automation-runs.json')))
        ->run('automation', 'view', 'Update Ubuntu servers');

    expect($result->exitCode)->toBe(0);
});

it('shows the plan under --dry-run and starts nothing', function () {
    $cli = automations()->withApi(api()->on('POST', 'v1/automations/7/runs', fixture('automation-run-dry-run.json')));

    $result = $cli->run('automation', 'run', '7', '--dry-run');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Select servers')
        ->and($result->stdout)->toContain('web-01')
        ->and($cli->api()->lastCall()['body'])->toBe(['dry_run' => true]);
});

it('refuses to start a run in a pipe without --yes', function () {
    $result = automations()->run('automation', 'run', '7');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('needs a confirmation');
});

it('starts a run', function () {
    $result = automations()
        ->withApi(api()->on('POST', 'v1/automations/7/runs', fixture('automation-run-create-201.json'), 201))
        ->run('automation', 'run', '7', '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Run '.substr(RUN, -6).' started · unolia automation watch '.substr(RUN, -6));
});

it('shows a run as one task per step on a terminal', function () {
    $result = automations()
        ->answers(['Run this automation now?' => true])
        ->withApi(api()
            ->on('POST', 'v1/automations/7/runs', fixture('automation-run-create-201.json'), 201)
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-running.json'))
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=20', fixture('run-01J9A2-completed.json')))
        ->run('automation', 'run', '7');

    $out = $result->stdout;

    expect($result->exitCode)->toBe(0)
        ->and($out)->toContain('Select servers')
        ->and($out)->toContain('2 servers')
        ->and($out)->toContain('apt update, apt upgrade')
        ->and($out)->toContain('web-01 · 1m 12s')
        ->and($out)->toContain('Reboot if the kernel changed')
        ->and($out)->toContain('Run '.substr(RUN, -6).' completed')
        ->and(strpos($out, 'Select servers'))->toBeLessThan(strpos($out, 'apt update'))
        ->and(strpos($out, 'apt update'))->toBeLessThan(strpos($out, 'Reboot'));
});

it('asks the question where the run stopped and carries on, on a terminal', function () {
    $cli = automations()
        ->answers(['Run this automation now?' => true, 'has a new kernel' => false])
        ->withApi(api()
            ->on('POST', 'v1/automations/7/runs', fixture('automation-run-create-201.json'), 201)
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-awaiting-input.json'))
            ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-completed.json')));

    $result = $cli->run('automation', 'run', '7');

    $out = $result->stdout;

    expect($result->exitCode)->toBe(0)
        ->and($result->stderr)->toContain('waiting for an answer')
        ->and($out)->toContain('answering')
        ->and($out)->toContain('Run '.substr(RUN, -6).' completed')
        // The step shows twice: once stopping at the question, once answering it.
        ->and(substr_count($out, 'Reboot if the kernel changed'))->toBe(2)
        ->and(strpos($out, 'answering'))->toBeLessThan(strpos($out, 'completed'))
        ->and($cli->api()->lastCall()['body'])->toBe(['inputs' => ['reboot' => false]]);
});

it('pre-ticks the default choices and sends the ids picked, not their names', function () {
    $cli = automations()
        ->answers(['Pick the servers to reboot' => ['102', '2']])
        ->withApi(api()
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-awaiting-servers.json'))
            ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-rebooted.json')));

    $result = $cli->run('automation', 'watch', RUN);

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Reboot which servers?')
        ->and($result->stdout)->toContain('2 servers rebooted')
        ->and($cli->api()->lastCall()['body'])->toBe(['inputs' => ['selected_server_ids' => ['102', '2']]]);
});

it('asks again when the API refuses the answer', function () {
    // The first answer names a server out of scope. The second ask has no
    // scripted answer and falls back to the block's default.
    $cli = automations()
        ->answers(['Pick the servers to reboot' => ['3']])
        ->withApi(api()
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-awaiting-servers.json'))
            ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-resume-422.json'), 422)
            ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-rebooted.json')));

    $result = $cli->run('automation', 'watch', RUN);

    expect($result->exitCode)->toBe(0)
        ->and($result->stderr)->toContain('cache-01 is not in scope')
        ->and($cli->api()->lastCall()['body'])->toBe(['inputs' => ['selected_server_ids' => ['2']]]);
});

it('does not take --yes for an answer', function () {
    $result = automations()
        ->withApi(api()->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-input.json')))
        ->run('automation', 'resume', RUN, '--yes');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --input');
});

it('exits 7 when a run parks waiting for an answer', function () {
    $result = automations()
        ->withApi(api()
            ->on('POST', 'v1/automations/7/runs', fixture('automation-run-create-201.json'), 201)
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-awaiting-input.json')))
        ->run('automation', 'run', '7', '--wait', '--yes');

    expect($result->exitCode)->toBe(7)
        ->and($result->stdout)->toContain('waiting for an answer');
});

it('resolves a run from its short id, the tail of the ULID', function () {
    $result = automations()
        ->withApi(api()
            ->on('GET', 'v1/automation-runs?ulid_suffix=V6W7Y8', fixture('automation-runs.json'))
            ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-completed.json')))
        ->run('automation', 'watch', 'v6w7y8');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('completed');
});

it('refuses a prefix that is too short', function () {
    $result = automations()->run('automation', 'watch', '01J');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('six characters');
});

it('answers a parked run from a flag and hands it back', function () {
    $cli = automations()->withApi(api()
        ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-input.json'))
        ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-completed.json')));

    $result = $cli->run('automation', 'resume', RUN, '--input', 'reboot=true');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Run '.substr(RUN, -6).' resumed · unolia automation watch '.substr(RUN, -6))
        ->and($cli->api()->lastCall()['body'])->toBe(['inputs' => ['reboot' => true]]);
});

it('takes choices by label in a flag and waits with --wait', function () {
    $cli = automations()->withApi(api()
        ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-servers.json'))
        ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-rebooted.json'))
        ->on('GET', 'v1/automation-runs/'.RUN.'?wait=0', fixture('run-01J9A2-rebooted.json')));

    $result = $cli->run('automation', 'resume', RUN, '--input', 'selected_server_ids=web-01, 2', '--wait');

    expect($result->exitCode)->toBe(0)
        ->and($cli->api()->calls()[1]['body'])->toBe(['inputs' => ['selected_server_ids' => ['102', '2']]]);
});

it('sends an empty list when nothing is picked', function () {
    $cli = automations()->withApi(api()
        ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-servers.json'))
        ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-rebooted.json')));

    $cli->run('automation', 'resume', RUN, '--input', 'selected_server_ids=');

    expect($cli->api()->lastCall()['body'])->toBe(['inputs' => ['selected_server_ids' => []]]);
});

it('lists the expected keys when a pipe resumes without inputs', function () {
    $result = automations()
        ->withApi(api()->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-input.json')))
        ->run('automation', 'resume', RUN);

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('missing --input')
        ->and($result->stderr)->toContain('reboot');
});

it('replays the steps down to the question, asks it, and follows the rest, on a terminal', function () {
    $cli = automations()
        ->answers(['has a new kernel' => true])
        ->withApi(api()
            ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-awaiting-input.json'))
            ->on('POST', 'v1/automation-runs/'.RUN.'/resume', fixture('run-01J9A2-completed.json')));

    $result = $cli->run('automation', 'resume', RUN);

    $out = $result->stdout;

    expect($result->exitCode)->toBe(0)
        ->and($out)->toContain('Select servers')
        ->and($out)->toContain('Reboot if the kernel changed')
        ->and($out)->toContain('Run '.substr(RUN, -6).' completed')
        ->and($cli->api()->lastCall()['body'])->toBe(['inputs' => ['reboot' => true]]);
});

it('lists runs and filters by state', function () {
    $cli = automations()->withApi(api()->on('GET', 'v1/automation-runs?state=completed', fixture('automation-runs.json')));

    $result = $cli->run('automation', 'runs', '--state', 'completed');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Update Ubuntu servers');
});

it('prints the log of a run', function () {
    $result = automations()
        ->withApi(api()
            ->on('GET', 'v1/automation-runs?ulid_suffix=V6W7Y8', fixture('automation-runs.json'))
            ->on('GET', 'v1/automation-runs/'.RUN.'/logs', fixture('run-01J9A2-logs.json')))
        ->run('automation', 'logs', 'V6W7Y8');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('apt upgrade finished on web-01');
});

it('replays a run', function () {
    $result = automations()
        ->withApi(api()->on('POST', 'v1/automation-runs/'.RUN.'/replay', fixture('automation-run-create-201.json')))
        ->run('automation', 'replay', RUN, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('started');
});

it('cancels a run with --yes', function () {
    $result = automations()
        ->withApi(api()->on('POST', 'v1/automation-runs/'.RUN.'/cancel', fixture('run-01J9A2-completed.json')))
        ->run('automation', 'cancel', RUN, '--yes');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('Cancelled run');
});

it('follows a log until the run settles, not until a poll is quiet', function () {
    $empty = ['data' => [], 'meta' => ['next_after' => '01J9A2K7Q4X0N1R8S3T5V6W7Z2']];

    $cli = automations()->withApi(api()
        ->on('GET', 'v1/automation-runs/'.RUN.'/logs?wait=20', fixture('run-01J9A2-logs.json'))
        // A quiet page while a step is still running: keep following.
        ->on('GET', 'v1/automation-runs/'.RUN.'/logs?after=01J9A2K7Q4X0N1R8S3T5V6W7Z2&wait=20', $empty)
        ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-running.json'))
        // Another quiet page, and now the run is over: stop.
        ->on('GET', 'v1/automation-runs/'.RUN.'/logs?after=01J9A2K7Q4X0N1R8S3T5V6W7Z2&wait=20', $empty)
        ->on('GET', 'v1/automation-runs/'.RUN, fixture('run-01J9A2-completed.json')));

    $result = $cli->run('automation', 'logs', RUN, '--follow');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('apt upgrade finished on web-01');

    $cli->api()->assertEverythingUsed();
});

it('says which run is already going when the automation refuses another', function () {
    $result = automations()
        ->withApi(api()->on('POST', 'v1/automations/7/runs', [
            'error' => ['code' => 'run_rejected', 'message' => 'Max concurrent runs reached for this Automation.', 'status' => 409, 'details' => ['running' => [RUN]]],
        ], 409))
        ->run('automation', 'run', '7', '--yes');

    expect($result->exitCode)->toBe(1)
        ->and($result->stderr)->toContain('Max concurrent runs reached')
        ->and($result->stderr)->toContain('unolia automation watch '.substr(RUN, -6).' follows it');
});
