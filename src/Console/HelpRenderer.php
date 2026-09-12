<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Application;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Runtime;
use Unolia\Cli\Version;

/**
 * Help in the shape people already know from gh: groups first, then usage, flags and examples.
 * Never prints a canonical colon name.
 */
final class HelpRenderer
{
    public function __construct(private readonly Runtime $runtime) {}

    public function root(Application $application): string
    {
        $sections = [];
        $sections[] = 'Unolia CLI '.Version::current();

        $identity = $this->identity();

        if ($identity !== null) {
            $sections[] = $identity;
        }

        $sections[] = '';
        $sections[] = 'USAGE';
        $sections[] = '  unolia <command> <subcommand> [flags]';

        foreach (Groups::order() as $group) {
            $entries = $this->entriesFor($application, $group);

            if ($entries === []) {
                continue;
            }

            $sections[] = '';
            $sections[] = $group;
            $sections[] = $this->columns($entries);
        }

        $sections[] = '';
        $sections[] = 'GLOBAL FLAGS';
        $sections[] = $this->options($application->getDefinition(), true);

        $sections[] = '';
        $sections[] = 'LEARN MORE';
        $sections[] = '  unolia help <command>';
        $sections[] = '  unolia help agents';
        $sections[] = '  https://unolia.com/docs/cli';

        return implode("\n", $sections);
    }

    public function namespaceHelp(Application $application, string $namespace): string
    {
        $entries = [];

        foreach ($application->all($namespace) as $name => $command) {
            if ($command->isHidden() || ! $this->belongsTo($command, $name, $namespace)) {
                continue;
            }

            $entries[substr($name, strlen($namespace) + 1)] = $command->getDescription();
        }

        ksort($entries);

        $sections = [];
        $summary = Groups::NAMESPACE_SUMMARY[$namespace] ?? null;

        if ($summary !== null) {
            $sections[] = ucfirst($namespace).': '.$summary;
            $sections[] = '';
        }

        $sections[] = 'USAGE';
        $sections[] = sprintf('  unolia %s <command> [flags]', $namespace);
        $sections[] = '';
        $sections[] = 'AVAILABLE COMMANDS';
        $sections[] = $this->columns($entries);
        $sections[] = '';
        $sections[] = 'LEARN MORE';
        $sections[] = sprintf('  unolia %s <command> --help', $namespace);

        return implode("\n", $sections);
    }

    public function command(Command $command): string
    {
        $sections = [];
        $description = $command->getDescription();

        if ($description !== '') {
            $sections[] = $description;
            $sections[] = '';
        }

        $help = $command->getProcessedHelp();

        if ($help !== '' && $help !== $description) {
            $sections[] = $help;
            $sections[] = '';
        }

        $sections[] = 'USAGE';
        $sections[] = '  '.$this->synopsis($command);

        $native = $command->getNativeDefinition();

        if ($native->getArguments() !== []) {
            $sections[] = '';
            $sections[] = 'ARGUMENTS';
            $sections[] = $this->arguments($native);
        }

        if ($native->getOptions() !== []) {
            $sections[] = '';
            $sections[] = 'FLAGS';
            $sections[] = $this->options($native, false);
        }

        $application = $command->getApplication();

        if ($application !== null) {
            $sections[] = '';
            $sections[] = 'INHERITED FLAGS';
            $sections[] = $this->options($application->getDefinition(), true);
        }

        $examples = $command instanceof BaseCommand ? $command->examples() : [];

        if ($examples !== []) {
            $sections[] = '';
            $sections[] = 'EXAMPLES';

            foreach ($examples as $label => $line) {
                if (is_string($label) && $label !== '' && ! is_numeric($label)) {
                    $sections[] = '  # '.$label;
                }

                $sections[] = '  $ '.$line;
            }
        }

        $learnMore = $command instanceof BaseCommand ? $command->learnMore() : null;

        if ($learnMore !== null) {
            $sections[] = '';
            $sections[] = 'LEARN MORE';
            $sections[] = '  '.$learnMore;
        }

        return implode("\n", $sections);
    }

    public function synopsis(Command $command): string
    {
        $parts = ['unolia', Groups::display((string) $command->getName())];

        foreach ($command->getNativeDefinition()->getArguments() as $argument) {
            $token = '<'.$argument->getName().'>';

            if ($argument->isArray()) {
                $token .= '...';
            }

            $parts[] = $argument->isRequired() ? $token : '['.$token.']';
        }

        $parts[] = '[flags]';

        return implode(' ', $parts);
    }

    /**
     * A command is listed under a namespace when it lives there, or when it is reachable
     * there through an alias from elsewhere, the way `auth login` reaches `login`. An
     * alias inside the same namespace, such as `site deploy`, stays silent.
     */
    private function belongsTo(Command $command, string $name, string $namespace): bool
    {
        $canonical = (string) $command->getName();

        if ($canonical === $name) {
            return true;
        }

        return in_array($name, $command->getAliases(), true)
            && Groups::namespaceOf($canonical) !== $namespace
            && ! str_contains($canonical, ':');
    }

    /**
     * @return array<string, string>
     */
    private function entriesFor(Application $application, string $group): array
    {
        $entries = [];

        foreach (Groups::MAP as $name => $mapped) {
            if ($mapped !== $group) {
                continue;
            }

            if (isset(Groups::NAMESPACE_SUMMARY[$name])) {
                $entries[$name] = Groups::NAMESPACE_SUMMARY[$name];

                continue;
            }

            if (! $application->has($name)) {
                continue;
            }

            $entries[$name] = $application->find($name)->getDescription();
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function columns(array $entries): string
    {
        $width = 0;

        foreach (array_keys($entries) as $name) {
            $width = max($width, mb_strwidth($name));
        }

        $lines = [];

        foreach ($entries as $name => $description) {
            $lines[] = rtrim('  '.str_pad($name, $width + 2).$description);
        }

        return implode("\n", $lines);
    }

    private function arguments(InputDefinition $definition): string
    {
        $entries = [];

        foreach ($definition->getArguments() as $argument) {
            $entries[$argument->getName()] = $this->withDefault($argument->getDescription(), $argument->getDefault());
        }

        return $this->columns($entries);
    }

    private function options(InputDefinition $definition, bool $compact): string
    {
        $entries = [];

        foreach ($definition->getOptions() as $option) {
            if ($compact && in_array($option->getName(), ['help', 'version', 'ansi', 'no-ansi', 'quiet', 'silent', 'verbose', 'no-interaction'], true)) {
                continue;
            }

            $entries[$this->optionName($option)] = $this->withDefault($option->getDescription(), $option->getDefault());
        }

        return $this->columns($entries);
    }

    private function optionName(InputOption $option): string
    {
        $name = $option->getShortcut() !== null ? '-'.$option->getShortcut().', ' : '    ';
        $name .= '--'.$option->getName();

        if ($option->acceptValue()) {
            $name .= $option->isValueOptional() ? ' [<value>]' : ' <value>';
        }

        return $name;
    }

    private function withDefault(string $description, mixed $default): string
    {
        if ($default === null || $default === false || $default === '' || $default === []) {
            return $description;
        }

        if (! is_scalar($default)) {
            return $description;
        }

        return trim($description.' (default '.(string) $default.')');
    }

    /**
     * Who you are and where you are, read from files only. Help never calls the API.
     */
    private function identity(): ?string
    {
        $host = $this->runtime->settings()->host();
        $entry = $this->runtime->hosts()->entry($host);

        if ($entry === []) {
            return null;
        }

        $parts = [];
        $name = $entry['name'] ?? null;
        $parts[] = is_string($name) && $name !== '' ? 'logged in as '.$name : 'logged in to '.$host;

        $team = $this->runtime->context()->teamSlug();

        if ($team !== null) {
            $parts[] = 'team '.$team;
        }

        $website = $this->runtime->context()->config()->website();

        if ($website !== null) {
            $parts[] = 'website '.$website;
        }

        return implode(' · ', $parts);
    }

    /** The block `unolia help agents` prints. */
    public static function agentNotes(): string
    {
        return <<<'NOTES'
        Unolia CLI for agents and scripts
        - Detects a pipe. Never prompts. Missing input exits 2 and lists candidates.
        - Add --json for structured output, --format ndjson for progress streams, --jq to filter.
        - Add --dry-run to any mutating command to see the plan without changing anything.
        - Add --yes to skip confirmations. Add --wait to block until the remote work finishes.
        - Exit codes: 0 ok, 1 remote failure, 2 usage, 3 auth, 4 not found, 5 forbidden or plan, 6 timeout, 7 awaiting input, 130 interrupted.
        - Context: .unolia/config.json in the repo, or --team/--project/--website, or UNOLIA_* env vars.
        - Auth: UNOLIA_TOKEN env var, or unolia login --with-token < token.txt. A person runs unolia login, which opens the browser.
        - Scopes: a 403 with insufficient_scope names the scope. unolia auth refresh --scopes <scope> adds it. unolia auth token prints the token in use.
        - Errors with --json are one JSON object on stderr: {"error":{"code","message","hint","exit_code"}}.
        - MCP: unolia mcp setup --local --agent <name> --yes connects an agent to the Unolia MCP server. --print shows the snippet.
        NOTES;
    }
}
