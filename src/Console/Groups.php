<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

/**
 * Static metadata about the command tree: which group a command belongs to, what a
 * namespace does in one line, and which old spellings still work.
 */
final class Groups
{
    public const CORE = 'CORE COMMANDS';

    public const RESOURCES = 'RESOURCE COMMANDS';

    public const LOCAL = 'LOCAL COMMANDS';

    public const UTILITY = 'UTILITY';

    /** @var array<string, string> canonical name or namespace => group */
    public const MAP = [
        'login' => self::CORE,
        'logout' => self::CORE,
        'status' => self::CORE,
        'auth' => self::CORE,
        'init' => self::CORE,
        'deploy' => self::CORE,
        'watch' => self::CORE,
        'api' => self::CORE,
        'me' => self::CORE,
        'open' => self::CORE,
        'project' => self::RESOURCES,
        'website' => self::RESOURCES,
        'deployment' => self::RESOURCES,
        'ci' => self::RESOURCES,
        'repo' => self::RESOURCES,
        'automation' => self::RESOURCES,
        'issue' => self::RESOURCES,
        'incident' => self::RESOURCES,
        'domain' => self::RESOURCES,
        'dns' => self::RESOURCES,
        'server' => self::RESOURCES,
        'env' => self::RESOURCES,
        'provider' => self::RESOURCES,
        'team' => self::RESOURCES,
        'configure' => self::LOCAL,
        'compare' => self::LOCAL,
        'mcp' => self::LOCAL,
        'completion' => self::UTILITY,
        'config' => self::UTILITY,
        'upgrade' => self::UTILITY,
        'help' => self::UTILITY,
    ];

    /** @var array<string, string> one line summary per namespace, printed in the top level list */
    public const NAMESPACE_SUMMARY = [
        'auth' => 'login · logout · status · refresh · token',
        'project' => 'list · view · switch · resolve',
        'website' => 'list · view · deploy · deployments · logs · domains · env',
        'deployment' => 'list · view · logs · watch',
        'ci' => 'list · view · watch · logs · rerun · cancel',
        'repo' => 'list · view',
        'automation' => 'list · view · run · replay · resume · cancel · runs · logs · watch',
        'issue' => 'list · view · fix · ignore · recheck',
        'incident' => 'list · view',
        'domain' => 'list · view',
        'dns' => 'list · add · set · edit · remove · watch · dig · check · export',
        'server' => 'list · view',
        'env' => 'map · view',
        'provider' => 'list · view · sync',
        'team' => 'list · switch · tokens',
        'configure' => 'herd · env',
        'compare' => 'local · versions',
        'mcp' => 'setup',
        'config' => 'get · set · list',
    ];

    /** @var array<string, list<string>> canonical name => aliases kept working on day one */
    public const ALIASES = [
        'login' => ['auth:login'],
        'logout' => ['auth:logout'],
        'status' => ['auth:status'],
        'website:deploy' => ['site:deploy'],
        'website:view' => ['site:view'],
        'website:logs' => ['site:logs'],
        'website:env' => ['site:env'],
        'website:domains' => ['site:domains'],
        'website:deployments' => ['site:deployments'],
        'team:list' => ['teams'],
        'domain:list' => ['domains'],
        'project:list' => ['projects'],
        'website:list' => ['site:list', 'websites', 'sites'],
        'deployment:list' => ['deployments'],
        'server:list' => ['servers'],
        'repo:list' => ['repos'],
        'automation:list' => ['automations'],
        'incident:list' => ['incidents'],
        'provider:list' => ['providers'],
        'dns:list' => ['dns', 'domain:records'],
        'dns:add' => ['domain:add'],
        'dns:set' => ['domain:set'],
        'dns:edit' => ['domain:update', 'dns:update'],
        'dns:remove' => ['domain:remove'],
        'dns:watch' => ['domain:watch'],
        'dns:dig' => ['dig', 'domain:dig'],
        'issue:list' => ['issues'],
        'ci:list' => ['ci'],
        'mcp:setup' => ['mcp'],
    ];

    /** @return list<string> */
    public static function order(): array
    {
        return [self::CORE, self::RESOURCES, self::LOCAL, self::UTILITY];
    }

    public static function display(string $canonical): string
    {
        return str_replace(':', ' ', $canonical);
    }

    public static function canonical(string $display): string
    {
        return str_replace(' ', ':', trim($display));
    }

    /** The group a canonical command name belongs to. */
    public static function groupFor(string $canonical): string
    {
        if (isset(self::MAP[$canonical])) {
            return self::MAP[$canonical];
        }

        $namespace = self::namespaceOf($canonical);

        return self::MAP[$namespace] ?? self::UTILITY;
    }

    public static function namespaceOf(string $canonical): string
    {
        $position = strpos($canonical, ':');

        return $position === false ? $canonical : substr($canonical, 0, $position);
    }

    /** @return list<string> */
    public static function aliasesFor(string $canonical): array
    {
        return self::ALIASES[$canonical] ?? [];
    }

    /** @return list<string> */
    public static function namespaces(): array
    {
        return array_keys(self::NAMESPACE_SUMMARY);
    }
}
