<?php

declare(strict_types=1);

namespace Unolia\Cli;

use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Unolia\Cli\Console\Commands\HelpCommand;
use Unolia\Cli\Console\Commands\ListCommand;
use Unolia\Cli\Console\ErrorRenderer;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Groups;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Console\Registry;

/**
 * The Unolia CLI application: multi word command names, the two faces, and one error
 * renderer for everything that goes wrong.
 */
final class Application extends SymfonyApplication
{
    public function __construct(private readonly Runtime $runtime)
    {
        parent::__construct('Unolia CLI', Version::current());

        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->setCatchErrors(false);
        $this->setDefaultCommand('list');
        $this->setCommandLoader(new FactoryCommandLoader(Registry::factories($this->runtime)));
    }

    public function runtime(): Runtime
    {
        return $this->runtime;
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput;
        $output ??= new ConsoleOutput;

        try {
            return parent::run($input, $output);
        } catch (Throwable $throwable) {
            return $this->renderFailure($throwable, $input, $output);
        }
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $typed = $input instanceof ArgvInput ? ($input->getRawTokens()[0] ?? null) : null;

        if ($input instanceof ArgvInput) {
            $input = $this->collapseMultiWordName($input);
        }

        if ($input instanceof ArgvInput) {
            $input = $this->routeBareNamespace($input);
        }

        if ($input->hasParameterOption('--no-input', true)) {
            $input->setInteractive(false);
        }

        $this->boot($input, $output);
        $this->hintColonForm(is_string($typed) ? $typed : null);

        try {
            return parent::doRun($input, $output);
        } catch (Throwable $throwable) {
            return $this->renderFailure($throwable, $input, $output);
        } finally {
            $this->drainNotices();
        }
    }

    /**
     * A bare namespace such as `unolia domain` lists that namespace, the way gh does.
     */
    public function find(string $name): Command
    {
        if (! $this->has($name) && in_array($name, Groups::namespaces(), true)) {
            $command = new ListCommand($this->runtime);
            $command->forNamespace($name);
            $command->setApplication($this);

            return $command;
        }

        return parent::find($name);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_ARGUMENT_VALUE && $input->getCompletionName() === 'command') {
            foreach (Registry::displayNames() as $name => $description) {
                $suggestions->suggestValue(new Suggestion($name, $description));
            }

            foreach (Groups::NAMESPACE_SUMMARY as $namespace => $summary) {
                $suggestions->suggestValue(new Suggestion($namespace, $summary));
            }

            return;
        }

        parent::complete($input, $suggestions);
    }

    protected function getDefaultCommands(): array
    {
        return [
            new HelpCommand($this->runtime),
            new ListCommand($this->runtime),
            new CompleteCommand,
            new DumpCompletionCommand,
        ];
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOptions([
            new InputOption('team', 't', InputOption::VALUE_REQUIRED, 'Team slug or id'),
            new InputOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project id or name'),
            new InputOption('website', 'w', InputOption::VALUE_REQUIRED, 'Website id or domain'),
            new InputOption('json', null, InputOption::VALUE_OPTIONAL, 'JSON output, optionally a comma list of fields', false),
            new InputOption('format', null, InputOption::VALUE_REQUIRED, 'table, json, ndjson, csv or yaml'),
            new InputOption('jq', null, InputOption::VALUE_REQUIRED, 'Filter the JSON output through jq'),
            new InputOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmations'),
            new InputOption('no-input', null, InputOption::VALUE_NONE, 'Never prompt, fail instead'),
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Show the plan without changing anything'),
            new InputOption('paginate', null, InputOption::VALUE_NONE, 'Follow every page'),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Page size, up to 100', '30'),
        ]);

        return $definition;
    }

    /**
     * Collapse the longest run of leading words that names a command: `website deploy`
     * becomes `website:deploy`, which is what Symfony has registered.
     */
    private function collapseMultiWordName(ArgvInput $input): ArgvInput
    {
        $tokens = $input->getRawTokens();
        $words = [];

        foreach ($tokens as $token) {
            if ($token === '' || $token[0] === '-') {
                break;
            }

            $words[] = $token;

            if (count($words) === 3) {
                break;
            }
        }

        for ($size = count($words); $size >= 2; $size--) {
            $candidate = implode(':', array_slice($words, 0, $size));

            if ($this->has($candidate)) {
                $rest = array_slice($tokens, $size);

                return new ArgvInput([$_SERVER['argv'][0] ?? 'unolia', $candidate, ...$rest]);
            }
        }

        return $input;
    }

    /**
     * `unolia domain` and `unolia domain --help` both mean "explain this namespace".
     * Routing them through help keeps Symfony's own help flag out of the way.
     */
    private function routeBareNamespace(ArgvInput $input): ArgvInput
    {
        $tokens = $input->getRawTokens();
        $first = $tokens[0] ?? null;

        if (! is_string($first) || $first === '' || $first[0] === '-') {
            return $input;
        }

        if ($this->has($first) || ! in_array($first, Groups::namespaces(), true)) {
            return $input;
        }

        $rest = array_values(array_filter(
            array_slice($tokens, 1),
            static fn (string $token): bool => $token !== '--help' && $token !== '-h',
        ));

        return new ArgvInput([$_SERVER['argv'][0] ?? 'unolia', 'help', $first, ...$rest]);
    }

    private function boot(InputInterface $input, OutputInterface $output): void
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if (! $this->runtime->isForced(Face::class)) {
            $this->runtime->provide(Face::class, Face::detect($input, $output, $this->runtime->environment()));
        }

        $face = $this->runtime->face();

        if (! $this->runtime->isForced(Out::class)) {
            $this->runtime->provide(Out::class, new Out($face, $output, $stderr));
        }

        $this->runtime->context()->bind($input);

        Prompt::setOutput($output);
        Prompt::interactive($face->interactive);
        Prompt::fallbackWhen(! $face->interactive);

        $this->drainNotices();
    }

    /** One time messages from the configuration layer, such as the v1 token migration. */
    private function drainNotices(): void
    {
        if (! $this->runtime->has(Out::class)) {
            return;
        }

        foreach ($this->runtime->hosts()->takeNotices() as $notice) {
            $this->runtime->out()->warn($notice);
        }
    }

    /**
     * The colon spelling keeps working for one major version, with a nudge towards the new one.
     */
    private function hintColonForm(?string $first): void
    {
        if ($first === null) {
            return;
        }

        $face = $this->runtime->face();

        if (! $face->interactive || $face->format !== Format::Table) {
            return;
        }

        if (! str_contains($first, ':') || ! $this->has($first)) {
            return;
        }

        $this->runtime->out()->note(sprintf('Tip: "unolia %s" is the new spelling.', Groups::display($first)));
    }

    private function renderFailure(Throwable $throwable, InputInterface $input, OutputInterface $output): int
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if (! $this->runtime->isForced(Out::class)) {
            if (! $this->runtime->isForced(Face::class)) {
                $this->runtime->provide(Face::class, Face::detect($input, $output, $this->runtime->environment()));
            }

            $this->runtime->provide(Out::class, new Out($this->runtime->face(), $output, $stderr));
        }

        $verbose = $output->isVerbose();

        $code = ErrorRenderer::render($throwable, $this->runtime->out(), $stderr, $verbose);

        return $code === ExitCode::Ok ? ExitCode::RemoteFailure->value : $code->value;
    }
}
