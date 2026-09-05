<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Mcp;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Mcp\Agent;
use Unolia\Cli\Mcp\AgentDetector;
use Unolia\Cli\Mcp\Installer;
use Unolia\Cli\Mcp\InstallStatus;
use Unolia\Cli\Mcp\InstallStep;
use Unolia\Cli\Mcp\Platform;
use Unolia\Cli\Mcp\Scope;

/**
 * Put the Unolia MCP server into the config of the AI agents on this machine.
 */
final class SetupCommand extends BaseCommand
{
    /** The --agent value and the multiselect entry that ask for the snippet instead of a file. */
    private const MANUAL = 'manual';

    protected function canonical(): string
    {
        return 'mcp:setup';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Connect your AI agents to the Unolia MCP server');
    }

    protected function define(): void
    {
        $this->addOption('global', null, InputOption::VALUE_NONE, 'Configure the agents for every project, in their user level config');
        $this->addOption('local', null, InputOption::VALUE_NONE, 'Configure the agents for this directory only');
        $this->addOption('agent', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An agent to configure, repeatable or comma separated: '.implode(', ', [...Agent::keys(), self::MANUAL]));
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'The MCP server URL, https://<host>/mcp/team by default');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the JSON snippet for any client and do nothing else');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Pick the agents and the scope at the terminal' => 'unolia mcp setup',
            'Claude Code and Cursor, for every project' => 'unolia mcp setup --global --agent claude,cursor',
            'This directory only, from a script or an agent' => 'unolia mcp setup --local --agent vscode --yes',
            'See what would be written' => 'unolia mcp setup --local --agent claude --dry-run',
            'Only the snippet' => 'unolia mcp setup --print',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $url = $this->url();

        if ($this->optionBool('print')) {
            $this->out()->document($this->snippet($url));

            return ExitCode::Ok;
        }

        $scope = $this->scope();
        $platform = Platform::current();
        $cwd = $this->runtime()->cwd();

        [$agents, $manual] = $this->agents($this->runtime()->get(AgentDetector::class), $platform, $cwd);

        $installer = $this->runtime()->get(Installer::class);
        $steps = array_map(
            static fn (Agent $agent): InstallStep => $installer->plan($agent, $scope, $platform, $url, $cwd),
            $agents,
        );
        $active = array_values(array_filter($steps, static fn (InstallStep $step): bool => $step->action !== 'skip'));

        if ($steps !== [] && ! $this->structured()) {
            $this->out()->list(
                array_map(static fn (InstallStep $step): array => ['name' => $step->agent->displayName(), 'plan' => $step->describe()], $steps),
                ['name' => 'Agent', 'plan' => 'Plan'],
            );
        }

        if ($this->dryRun()) {
            if ($this->structured()) {
                $record = [
                    'url' => $url,
                    'scope' => $scope->value,
                    'plan' => array_map(static fn (InstallStep $step): array => $step->toArray(), $steps),
                    'dry_run' => true,
                ];

                if ($manual) {
                    $record['snippet'] = $this->snippet($url);
                }

                $this->out()->record($record);
            } else {
                $this->out()->note('Nothing was written.');
            }

            return ExitCode::Ok;
        }

        $count = count($active);

        if ($count > 0 && ! $this->confirmOrPlan(sprintf('Configure %d agent%s?', $count, $count === 1 ? '' : 's'))) {
            $this->out()->note('Nothing was written.');

            return ExitCode::Ok;
        }

        $rows = [];
        $notes = [];
        $failed = false;

        foreach ($steps as $step) {
            $result = $installer->apply($step, $url);
            $failed = $failed || $result->status === InstallStatus::Failed;

            if ($result->status === InstallStatus::Installed) {
                $notes = [...$notes, ...$step->agent->notes($scope)];
            }

            $rows[] = [
                'agent' => $step->agent->value,
                'name' => $step->agent->displayName(),
                'status' => $result->status->value,
                'detail' => $result->detail,
            ];
        }

        $this->report($url, $scope, $rows, $this->notes($rows, $notes), $manual);

        return $failed ? ExitCode::RemoteFailure : ExitCode::Ok;
    }

    private function url(): string
    {
        $url = $this->optionString('url')
            ?? $this->runtime()->env('UNOLIA_MCP_URL')
            ?? sprintf('%s://%s/mcp/team', $this->runtime()->insecure() ? 'http' : 'https', $this->runtime()->host());

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw CliError::usage(sprintf('invalid MCP server URL %s', $url), 'Pass --url https://<host>/mcp/team.');
        }

        return $url;
    }

    private function scope(): Scope
    {
        $global = $this->optionBool('global');
        $local = $this->optionBool('local');

        if ($global && $local) {
            throw CliError::usage('pass either --global or --local, not both');
        }

        if ($global) {
            return Scope::Global;
        }

        if ($local) {
            return Scope::Local;
        }

        $options = [];

        foreach (Scope::cases() as $scope) {
            $options[$scope->value] = $scope->label();
        }

        return Scope::from($this->ask()->select(
            'Where should the Unolia connector be installed?',
            $options,
            '--global or --local',
            Scope::Global->value,
        ));
    }

    /**
     * The agents named by --agent, or picked at the terminal with the detected ones preselected.
     *
     * @return array{0: list<Agent>, 1: bool} the agents, and whether the snippet was asked for
     */
    private function agents(AgentDetector $detector, Platform $platform, string $cwd): array
    {
        $keys = [];

        foreach ($this->optionList('agent') as $value) {
            foreach (explode(',', $value) as $key) {
                if (trim($key) !== '') {
                    $keys[] = trim($key);
                }
            }
        }

        if ($keys === []) {
            $options = [];

            foreach (Agent::cases() as $agent) {
                $options[$agent->value] = $agent->displayName();
            }

            $options[self::MANUAL] = 'Another client, show me the JSON snippet';

            $keys = $this->ask()->multiselect(
                'Which AI agents should be configured?',
                $options,
                '--agent',
                array_map(static fn (Agent $agent): string => $agent->value, $detector->detected($platform, $cwd)),
                'The agents found on this machine are preselected.',
            );
        }

        $agents = [];
        $manual = false;
        $known = [...Agent::keys(), self::MANUAL];

        foreach (array_unique($keys) as $key) {
            if ($key === self::MANUAL) {
                $manual = true;

                continue;
            }

            $agents[] = Agent::tryFrom($key) ?? throw CliError::usage(
                sprintf('unknown agent %s', $key),
                'Known agents: '.implode(', ', $known),
                ['candidates' => $known],
            );
        }

        return [$agents, $manual];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $agentNotes
     * @return list<string>
     */
    private function notes(array $rows, array $agentNotes): array
    {
        $installed = array_filter($rows, static fn (array $row): bool => $row['status'] === InstallStatus::Installed->value);

        if ($installed === []) {
            return [];
        }

        return array_values(array_unique([
            'The first connection opens your browser to sign in to Unolia. No token is stored in the agent config.',
            'Restart your editors and agents so they pick up the new server.',
            ...$agentNotes,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $notes
     */
    private function report(string $url, Scope $scope, array $rows, array $notes, bool $manual): void
    {
        if ($this->structured()) {
            $record = ['url' => $url, 'scope' => $scope->value, 'results' => $rows, 'notes' => $notes];

            if ($manual) {
                $record['snippet'] = $this->snippet($url);
            }

            $this->out()->record($record);

            return;
        }

        if ($rows !== []) {
            $this->out()->line('');
            $this->out()->list($rows, ['name' => 'Agent', 'status' => 'Status', 'detail' => 'Detail']);
        }

        foreach ($notes as $note) {
            $this->out()->note($note);
        }

        if ($manual) {
            $this->out()->line('');
            $this->out()->line('Add this to the MCP config of your client:');
            $this->out()->document($this->snippet($url));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snippet(string $url): array
    {
        return ['mcpServers' => [Agent::SERVER_KEY => ['type' => 'http', 'url' => $url]]];
    }
}
