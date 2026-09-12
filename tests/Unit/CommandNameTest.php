<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Support\CliTester;
use Unolia\Cli\Application;
use Unolia\Cli\Console\Registry;
use Unolia\Cli\Runtime;

function application(): Application
{
    return new Application(Runtime::fromEnvironment(['HOME' => sys_get_temp_dir()], sys_get_temp_dir()));
}

function resolvedName(string ...$argv): string
{
    $application = application();
    $reflection = new ReflectionMethod($application, 'collapseMultiWordName');
    /** @var ArgvInput $collapsed */
    $collapsed = $reflection->invoke($application, new ArgvInput(['unolia', ...$argv]));

    return (string) $collapsed->getFirstArgument();
}

it('collapses two words into a colon name', function () {
    expect(resolvedName('domain', 'list'))->toBe('domain:list');
});

it('keeps a colon name as it is', function () {
    expect(resolvedName('domain:list'))->toBe('domain:list');
});

it('keeps the options after a collapsed name', function () {
    $application = application();
    $reflection = new ReflectionMethod($application, 'collapseMultiWordName');
    /** @var ArgvInput $collapsed */
    $collapsed = $reflection->invoke($application, new ArgvInput(['unolia', 'website', 'deploy', '--wait']));

    expect($collapsed->getFirstArgument())->toBe('website:deploy')
        ->and($collapsed->hasParameterOption('--wait'))->toBeTrue();
});

it('leaves an argument alone when there is no such command', function () {
    expect(resolvedName('deploy', 'staging'))->toBe('deploy');
});

it('collapses through an alias', function () {
    expect(resolvedName('site', 'deploy'))->toBe('site:deploy');
});

it('finds a command through its alias', function () {
    expect(application()->find('teams')->getName())->toBe('team:list')
        ->and(application()->find('dig')->getName())->toBe('dns:dig')
        ->and(application()->find('domains')->getName())->toBe('domain:list')
        ->and(application()->find('dns')->getName())->toBe('dns:list')
        ->and(application()->find('providers')->getName())->toBe('provider:list')
        ->and(application()->find('websites')->getName())->toBe('website:list')
        ->and(application()->find('deployments')->getName())->toBe('deployment:list')
        ->and(application()->find('ci')->getName())->toBe('ci:list');
});

it('runs the top level list when nothing is asked', function () {
    $application = application();
    $output = new NullOutput;

    expect($application->run(new ArgvInput(['unolia']), $output))->toBe(0);
});

it('registers every command in the tree', function () {
    $application = application();

    foreach (array_keys(Registry::COMMANDS) as $name) {
        expect($application->has($name))->toBeTrue("missing {$name}");
    }
});

it('only nudges towards the space spelling when a colon was typed', function () {
    $spaced = CliTester::make()->interactive()->run('domain', 'list', '--help');
    $colon = CliTester::make()->interactive()->run('domain:list', '--help');

    expect($spaced->stdout)->not->toContain('new spelling')
        ->and($colon->stdout)->toContain('Tip: "unolia domain list" is the new spelling.');
});
