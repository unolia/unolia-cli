<?php

declare(strict_types=1);

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Tests\Support\CliTester;
use Unolia\Cli\Application;
use Unolia\Cli\Runtime;

function suggestionsFor(string $line): array
{
    $application = new Application(Runtime::fromEnvironment(['HOME' => sys_get_temp_dir()], sys_get_temp_dir()));
    $suggestions = new CompletionSuggestions;

    $input = CompletionInput::fromString($line, 1);
    $input->bind($application->getDefinition());

    $application->complete($input, $suggestions);

    return array_map(strval(...), $suggestions->getValueSuggestions());
}

it('suggests display names and namespaces, never colon names', function () {
    $values = suggestionsFor('unolia ');

    expect($values)->toContain('website deploy')
        ->and($values)->toContain('domain')
        ->and($values)->toContain('teams')
        ->and($values)->not->toContain('website:deploy');
});

it('dumps a completion script', function () {
    $result = CliTester::make()->run('completion', 'zsh');

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toContain('compdef');
});
