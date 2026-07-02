<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Mcp\Agent;
use Unolia\UnoliaCLI\Mcp\AgentDetector;
use Unolia\UnoliaCLI\Mcp\Installer;
use Unolia\UnoliaCLI\Mcp\InstallResult;
use Unolia\UnoliaCLI\Mcp\InstallStatus;
use Unolia\UnoliaCLI\Mcp\Platform;
use Unolia\UnoliaCLI\Mcp\Scope;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class McpCommand extends Command
{
    protected $signature = 'mcp
        {action=setup : Action to perform (currently: setup)}
        {--global : Configure agents for all your projects (user-level config)}
        {--local : Configure agents for the current directory only}
        {--agents= : Comma-separated agents to configure (claude,cursor,vscode,codex,gemini,junie,kiro,opencode,amp)}
        {--url= : Override the MCP server URL}
        {--print : Print the manual JSON snippet and exit}';

    protected $description = 'Connect your AI agents to the Unolia MCP server';

    /** The --agents= / multiselect key for the manual snippet option. */
    private const MANUAL = 'manual';

    public function handle(AgentDetector $detector, Installer $installer): int
    {
        return match ($this->argument('action')) {
            'setup' => $this->setup($detector, $installer),
            default => $this->invalidAction(),
        };
    }

    private function setup(AgentDetector $detector, Installer $installer): int
    {
        $url = $this->option('url') ?: config('settings.mcp.url');

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->error("Invalid MCP server URL: {$url}");

            return self::FAILURE;
        }

        if ($this->option('print')) {
            $this->line($this->manualSnippet($url));

            return self::SUCCESS;
        }

        $scope = $this->resolveScope();

        if ($scope === null) {
            return self::FAILURE;
        }

        $platform = Platform::current();
        $cwd = (string) getcwd();

        $selection = $this->selectAgents($detector, $platform, $cwd);

        if ($selection === null) {
            return self::FAILURE;
        }

        [$agents, $manual] = $selection;

        $results = [];

        foreach ($agents as $agent) {
            $results[] = [$agent, $result = $installer->install($agent, $scope, $platform, $url, $cwd)];
            $this->renderResult($agent, $result);
        }

        if ($manual) {
            info('Add this to your client\'s MCP config file:');
            $this->line($this->manualSnippet($url));
        }

        $this->renderNotes($results, $scope);

        return collect($results)->contains(fn (array $entry) => $entry[1]->status === InstallStatus::Failed)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolveScope(): ?Scope
    {
        if ($this->option('global') && $this->option('local')) {
            $this->error('Pass either --global or --local, not both.');

            return null;
        }

        if ($this->option('global')) {
            return Scope::Global;
        }

        if ($this->option('local')) {
            return Scope::Local;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Pass --global or --local when running non-interactively.');

            return null;
        }

        return Scope::from(select(
            label: 'Where should the Unolia connector be installed?',
            options: [
                Scope::Global->value => 'All projects (global — recommended)',
                Scope::Local->value => 'This directory only (local)',
            ],
            default: Scope::Global->value,
        ));
    }

    /**
     * @return array{0: list<Agent>, 1: bool}|null agents to configure + whether the manual snippet was requested
     */
    private function selectAgents(AgentDetector $detector, Platform $platform, string $cwd): ?array
    {
        if ($this->option('agents')) {
            $agents = [];

            foreach (explode(',', (string) $this->option('agents')) as $key) {
                $agent = Agent::tryFrom($key = trim($key));

                if ($agent === null) {
                    $this->error("Unknown agent \"{$key}\". Valid agents: ".implode(',', array_column(Agent::cases(), 'value')));

                    return null;
                }

                $agents[] = $agent;
            }

            return [$agents, false];
        }

        if (! $this->input->isInteractive()) {
            $this->error('Pass --agents=<'.implode(',', array_column(Agent::cases(), 'value')).'> when running non-interactively.');

            return null;
        }

        $detected = collect(Agent::cases())
            ->filter(fn (Agent $agent) => $detector->detect($agent, $platform, $cwd))
            ->map(fn (Agent $agent) => $agent->value);

        $options = collect(Agent::cases())
            ->mapWithKeys(fn (Agent $agent) => [$agent->value => $agent->displayName()])
            ->put(self::MANUAL, 'Other (show a manual JSON snippet)');

        $selected = multiselect(
            label: 'Which AI agents would you like to configure?',
            options: $options->all(),
            default: $detected->all(),
            scroll: 10,
            required: true,
            hint: 'Detected agents are pre-selected.',
        );

        return [
            array_values(array_filter(array_map(Agent::tryFrom(...), $selected))),
            in_array(self::MANUAL, $selected, true),
        ];
    }

    private function renderResult(Agent $agent, InstallResult $result): void
    {
        $symbol = match ($result->status) {
            InstallStatus::Installed => '<fg=green>✓</>',
            InstallStatus::Skipped => '<fg=yellow>–</>',
            InstallStatus::Failed => '<fg=red>✗</>',
        };

        $this->line("  {$symbol} ".str_pad($agent->displayName(), 20).' <fg=gray>'.$result->detail.'</>');
    }

    /**
     * @param  list<array{0: Agent, 1: InstallResult}>  $results
     */
    private function renderNotes(array $results, Scope $scope): void
    {
        $installed = collect($results)->filter(fn (array $entry) => $entry[1]->status === InstallStatus::Installed);

        if ($installed->isNotEmpty()) {
            $notes = $installed
                ->flatMap(fn (array $entry) => $entry[0]->notes($scope))
                ->unique()
                ->prepend('Restart your editors/agents to pick up the new server.')
                ->prepend('The first connection opens your browser to sign in to Unolia (OAuth) — no tokens are stored in your config.');

            $this->newLine();
            $notes->each(fn (string $note) => $this->line("  <fg=gray>•</> {$note}"));
        }
    }

    private function manualSnippet(string $url): string
    {
        return (string) json_encode([
            'mcpServers' => [
                Agent::SERVER_KEY => ['type' => 'http', 'url' => $url],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function invalidAction(): int
    {
        $this->error(sprintf('Unknown action "%s". Available actions: setup', $this->argument('action')));

        return self::FAILURE;
    }
}
