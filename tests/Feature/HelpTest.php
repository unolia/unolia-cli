<?php

declare(strict_types=1);

use Tests\Support\CliTester;
use Unolia\Cli\Application;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\Registry;
use Unolia\Cli\Runtime;

it('prints the command tree with no arguments', function () {
    $result = CliTester::make()->run();

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('CORE COMMANDS')
        ->and($result->stdout)->toContain('RESOURCE COMMANDS')
        ->and($result->stdout)->toContain('LOCAL COMMANDS')
        ->and($result->stdout)->toContain('GLOBAL FLAGS')
        ->and($result->stdout)->toContain('unolia <command> <subcommand> [flags]');
});

it('never prints a colon name in the tree', function () {
    $result = CliTester::make()->run('--help');

    expect($result->stdout)->not->toContain('website:deploy')
        ->and($result->stdout)->not->toContain('domain:list');
});

it('explains a namespace', function () {
    foreach ([['domain'], ['domain', '--help'], ['help', 'domain']] as $argv) {
        $result = CliTester::make()->run(...$argv);

        expect($result->exitCode)->toBe(0, implode(' ', $argv))
            ->and($result->stdout)->toContain('AVAILABLE COMMANDS')
            ->and($result->stdout)->toContain('records')
            ->and($result->stdout)->toContain('list');
    }
});

it('explains one command with its examples', function () {
    $result = CliTester::make()->run('website', 'deploy', '--help');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('USAGE')
        ->and($result->stdout)->toContain('unolia website deploy [<website>] [flags]')
        ->and($result->stdout)->toContain('ARGUMENTS')
        ->and($result->stdout)->toContain('--wait')
        ->and($result->stdout)->toContain('INHERITED FLAGS')
        ->and($result->stdout)->toContain('EXAMPLES')
        ->and($result->stdout)->toContain('$ unolia deploy')
        ->and($result->stdout)->toContain('LEARN MORE');
});

it('reads help for a multi word name', function () {
    $result = CliTester::make()->run('help', 'website', 'deploy');

    expect($result->stdout)->toContain('unolia website deploy');
});

it('prints the agent notes', function () {
    $result = CliTester::make()->run('help', 'agents');

    expect($result->stdout)->toContain('Never prompts')
        ->and($result->stdout)->toContain('Exit codes: 0 ok, 1 remote failure');
});

it('exits 2 for a command that does not exist', function () {
    $result = CliTester::make()->run('help', 'wobble');

    expect($result->exitCode)->toBe(2)
        ->and($result->stderr)->toContain('there is no wobble command');
});

it('every command has a description and at least two examples', function () {
    $runtime = Runtime::fromEnvironment(['HOME' => sys_get_temp_dir()], sys_get_temp_dir());
    $application = new Application($runtime);

    foreach (array_keys(Registry::COMMANDS) as $name) {
        $command = $application->find($name);

        expect($command->getDescription())->not->toBe('', $name);

        if ($name === 'docs:generate') {
            continue;
        }

        expect($command)->toBeInstanceOf(BaseCommand::class);
        expect(count($command->examples()))->toBeGreaterThanOrEqual(2, $name);
    }
});
