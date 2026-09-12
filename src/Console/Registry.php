<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Unolia\Cli\Command;
use Unolia\Cli\Runtime;

/**
 * Every command, keyed by its canonical name and by each alias, as lazy factories so an
 * invocation only ever builds the command it runs.
 */
final class Registry
{
    /** @var array<string, class-string<Command\BaseCommand>> */
    public const COMMANDS = [
        'login' => Command\Core\LoginCommand::class,
        'logout' => Command\Core\LogoutCommand::class,
        'me' => Command\Core\MeCommand::class,
        'status' => Command\Core\StatusCommand::class,
        'init' => Command\Core\InitCommand::class,
        'deploy' => Command\Core\DeployCommand::class,
        'watch' => Command\Core\WatchCommand::class,
        'api' => Command\Core\ApiCommand::class,
        'open' => Command\Core\OpenCommand::class,

        'auth:refresh' => Command\Auth\RefreshCommand::class,
        'auth:token' => Command\Auth\TokenCommand::class,

        'project:list' => Command\Project\ListCommand::class,
        'project:view' => Command\Project\ViewCommand::class,
        'project:switch' => Command\Project\SwitchCommand::class,
        'project:resolve' => Command\Project\ResolveCommand::class,

        'website:list' => Command\Website\ListCommand::class,
        'website:view' => Command\Website\ViewCommand::class,
        'website:deploy' => Command\Website\DeployCommand::class,
        'website:deployments' => Command\Website\DeploymentsCommand::class,
        'website:logs' => Command\Website\LogsCommand::class,
        'website:domains' => Command\Website\DomainsCommand::class,
        'website:env' => Command\Website\EnvCommand::class,

        'deployment:list' => Command\Deployment\ListCommand::class,
        'deployment:view' => Command\Deployment\ViewCommand::class,
        'deployment:logs' => Command\Deployment\LogsCommand::class,
        'deployment:watch' => Command\Deployment\WatchCommand::class,

        'ci:list' => Command\Ci\ListCommand::class,
        'ci:view' => Command\Ci\ViewCommand::class,
        'ci:watch' => Command\Ci\WatchCommand::class,
        'ci:logs' => Command\Ci\LogsCommand::class,
        'ci:rerun' => Command\Ci\RerunCommand::class,
        'ci:cancel' => Command\Ci\CancelCommand::class,

        'repo:list' => Command\Repo\ListCommand::class,
        'repo:view' => Command\Repo\ViewCommand::class,

        'automation:list' => Command\Automation\ListCommand::class,
        'automation:view' => Command\Automation\ViewCommand::class,
        'automation:run' => Command\Automation\RunCommand::class,
        'automation:replay' => Command\Automation\ReplayCommand::class,
        'automation:resume' => Command\Automation\ResumeCommand::class,
        'automation:cancel' => Command\Automation\CancelCommand::class,
        'automation:runs' => Command\Automation\RunsCommand::class,
        'automation:logs' => Command\Automation\LogsCommand::class,
        'automation:watch' => Command\Automation\WatchCommand::class,

        'issue:list' => Command\Issue\ListCommand::class,
        'issue:view' => Command\Issue\ViewCommand::class,
        'issue:fix' => Command\Issue\FixCommand::class,
        'issue:ignore' => Command\Issue\IgnoreCommand::class,
        'issue:recheck' => Command\Issue\RecheckCommand::class,

        'incident:list' => Command\Incident\ListCommand::class,
        'incident:view' => Command\Incident\ViewCommand::class,

        'domain:list' => Command\Domain\ListCommand::class,
        'domain:view' => Command\Domain\ViewCommand::class,
        'dns:list' => Command\Dns\ListCommand::class,
        'dns:add' => Command\Dns\AddCommand::class,
        'dns:set' => Command\Dns\SetCommand::class,
        'dns:edit' => Command\Dns\EditCommand::class,
        'dns:remove' => Command\Dns\RemoveCommand::class,
        'dns:watch' => Command\Dns\WatchCommand::class,
        'dns:dig' => Command\Dns\DigCommand::class,
        'dns:check' => Command\Dns\CheckCommand::class,
        'dns:export' => Command\Dns\ExportCommand::class,

        'server:list' => Command\Server\ListCommand::class,
        'server:view' => Command\Server\ViewCommand::class,

        'env:map' => Command\Env\MapCommand::class,
        'env:view' => Command\Env\ViewCommand::class,

        'provider:list' => Command\Provider\ListCommand::class,
        'provider:view' => Command\Provider\ViewCommand::class,
        'provider:sync' => Command\Provider\SyncCommand::class,
        'provider:fix' => Command\Provider\FixCommand::class,

        'team:list' => Command\Team\ListCommand::class,
        'team:switch' => Command\Team\SwitchCommand::class,
        'team:tokens' => Command\Team\TokensCommand::class,

        'configure:herd' => Command\Local\ConfigureHerdCommand::class,
        'configure:env' => Command\Local\ConfigureEnvCommand::class,
        'compare:local' => Command\Local\CompareLocalCommand::class,
        'compare:versions' => Command\Local\CompareVersionsCommand::class,
        'mcp:setup' => Command\Mcp\SetupCommand::class,

        'config:get' => Command\Utility\ConfigGetCommand::class,
        'config:set' => Command\Utility\ConfigSetCommand::class,
        'config:list' => Command\Utility\ConfigListCommand::class,
        'upgrade' => Command\Utility\UpgradeCommand::class,
        'docs:generate' => Command\Utility\DocsCommand::class,
    ];

    /**
     * @return array<string, callable(): SymfonyCommand>
     */
    public static function factories(Runtime $runtime): array
    {
        $factories = [];

        foreach (self::COMMANDS as $name => $class) {
            $factories[$name] = static fn (): SymfonyCommand => new $class($runtime);
        }

        foreach (self::aliasMap() as $alias => $canonical) {
            $class = self::COMMANDS[$canonical];
            $factories[$alias] = static fn (): SymfonyCommand => new $class($runtime);
        }

        return $factories;
    }

    /**
     * Every alias the loader must answer to, so `unolia teams` and `unolia site deploy`
     * find their command without instantiating anything first.
     *
     * @return array<string, string> alias => canonical
     */
    public static function aliasMap(): array
    {
        $aliases = [];

        foreach (array_keys(self::COMMANDS) as $name) {
            foreach (Groups::aliasesFor($name) as $alias) {
                $aliases[$alias] = $name;
            }
        }

        return $aliases;
    }

    /**
     * Display names with their descriptions, used by shell completion.
     *
     * @return array<string, string>
     */
    public static function displayNames(): array
    {
        $names = [];

        foreach (array_keys(self::COMMANDS) as $name) {
            if ($name === 'docs:generate') {
                continue;
            }

            $names[Groups::display($name)] = Groups::NAMESPACE_SUMMARY[Groups::namespaceOf($name)] ?? '';
        }

        foreach (array_keys(self::aliasMap()) as $alias) {
            $names[Groups::display($alias)] = '';
        }

        $names['help'] = 'Help for any command';
        $names['completion'] = 'Shell completion for bash, zsh, fish';

        return $names;
    }
}
