<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Local;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Projects\ProjectVersions;
use Unolia\Cli\Api\Requests\Servers\ShowServer;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Local\ComposerLock;
use Unolia\Cli\Local\DotEnv;
use Unolia\Cli\Local\Node;
use Unolia\Cli\Local\Php;
use Unolia\Cli\Support\Arr;

/**
 * What this machine runs, next to what production runs.
 */
final class CompareLocalCommand extends BaseCommand
{
    use ResolvesTargets;

    private const DEFAULT_COMPONENTS = ['php', 'laravel', 'composer'];

    private const ALL_COMPONENTS = ['php', 'laravel', 'composer', 'database', 'node'];

    protected function canonical(): string
    {
        return 'compare:local';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Compare this machine with production');
    }

    protected function define(): void
    {
        $this->addOption('env', null, InputOption::VALUE_REQUIRED, 'Which environment to compare against');
        $this->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma list of components');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Include components production does not report');
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Warnings also fail');
    }

    public function examples(): array
    {
        return [
            'Am I in sync' => 'unolia compare local',
            'Everything' => 'unolia compare local --all',
            'In CI' => 'unolia compare local --strict --json',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $websiteId = $this->targetWebsite();
        $website = $this->fetch(new ShowWebsite($websiteId));
        $serverId = Arr::get($website, 'managed_server.id');
        $server = is_numeric($serverId) ? $this->fetch(new ShowServer((int) $serverId)) : [];
        $projectId = Arr::get($website, 'project.id');
        // Only the framework row is compared; the runtime comes from the website itself.
        $versions = is_numeric($projectId) ? $this->collection(new ProjectVersions((int) $projectId, ['name' => 'laravel/framework'])) : [];

        $root = $this->runtime()->context()->config()->rootDir()
            ?? $this->runtime()->context()->git()->root()
            ?? $this->runtime()->cwd();

        $components = [];

        foreach ($this->wanted() as $name) {
            $components[] = match ($name) {
                'php' => $this->php($root, $website),
                'laravel' => $this->laravel($root, $versions),
                'composer' => $this->composer(),
                'database' => $this->database($root, $server),
                default => $this->node(),
            };
        }

        if (! $this->optionBool('all') && $this->optionString('only') === null) {
            $components = array_values(array_filter(
                $components,
                static fn (array $component): bool => $component['verdict'] !== 'unknown' || $component['name'] === 'composer',
            ));
        }

        $mismatches = array_values(array_filter($components, static fn (array $c): bool => $c['verdict'] === 'mismatch'));
        $warnings = array_values(array_filter($components, static fn (array $c): bool => $c['verdict'] === 'warning'));
        $suggestions = $this->suggestions($mismatches);

        $summary = sprintf(
            '%d match, %d warning%s, %d mismatch%s',
            count(array_filter($components, static fn (array $c): bool => $c['verdict'] === 'match')),
            count($warnings),
            count($warnings) === 1 ? '' : 's',
            count($mismatches),
            count($mismatches) === 1 ? '' : 'es',
        );

        if ($this->structured()) {
            $this->out()->record([
                'target' => ['website' => $websiteId, 'domain' => $website['domain'] ?? null],
                'components' => $components,
                'summary' => $summary,
                'suggestions' => $suggestions,
            ]);
        } else {
            $this->out()->list(
                $components,
                ['marker' => '', 'name' => 'Component', 'local' => 'Local', 'production' => 'Production', 'note' => 'Note'],
                static fn (array $row): array => [
                    'marker' => match ($row['verdict']) {
                        'match' => '✓',
                        'warning' => '!',
                        'mismatch' => '✗',
                        default => '?',
                    },
                    'local' => (string) ($row['local'] ?? 'unknown'),
                    'production' => (string) ($row['production'] ?? 'unknown'),
                ],
            );

            $this->out()->line('');
            $this->out()->note($summary);

            foreach ($suggestions as $suggestion) {
                $this->out()->note($suggestion);
            }
        }

        if ($mismatches !== []) {
            return ExitCode::RemoteFailure;
        }

        return $this->optionBool('strict') && $warnings !== [] ? ExitCode::RemoteFailure : ExitCode::Ok;
    }

    private function targetWebsite(): int
    {
        $environment = $this->optionString('env');

        if ($environment === null) {
            return $this->websiteId();
        }

        $environments = $this->runtime()->context()->environments();

        if (! isset($environments[$environment])) {
            throw CliError::notFound(
                sprintf('there is no %s environment here', $environment),
                'Known environments: '.implode(', ', array_keys($environments)),
                ['candidates' => array_keys($environments)],
            );
        }

        return $environments[$environment];
    }

    /**
     * @return list<string>
     */
    private function wanted(): array
    {
        $only = $this->optionString('only');

        if ($only !== null) {
            $names = [];

            foreach (explode(',', $only) as $name) {
                $name = trim($name);

                if (! in_array($name, self::ALL_COMPONENTS, true)) {
                    throw CliError::usage(
                        sprintf('%s is not a component', $name),
                        'Known components: '.implode(', ', self::ALL_COMPONENTS),
                        ['candidates' => self::ALL_COMPONENTS],
                    );
                }

                $names[] = $name;
            }

            return $names;
        }

        return $this->optionBool('all') ? self::ALL_COMPONENTS : self::DEFAULT_COMPONENTS;
    }

    /**
     * @param  array<string, mixed>  $website
     * @return array<string, mixed>
     */
    private function php(string $root, array $website): array
    {
        $local = $this->runtime()->get(Php::class)->localVersion($root);
        $production = is_string($website['php_version'] ?? null) ? $website['php_version'] : null;
        $localShort = Php::majorMinor($local);

        return $this->component('php', $local, $production, match (true) {
            $local === null || $production === null => 'unknown',
            $localShort === $production => 'match',
            default => 'mismatch',
        }, 'herd php -v', 'website php_version');
    }

    /**
     * @param  list<array<string, mixed>>  $versions
     * @return array<string, mixed>
     */
    private function laravel(string $root, array $versions): array
    {
        $local = $this->runtime()->get(ComposerLock::class)->packageVersion($root, 'laravel/framework');
        $production = $this->versionOf($versions, 'laravel/framework');

        return $this->component('laravel', $local, $production, $this->compareSemver($local, $production), 'composer.lock', 'project versions');
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $local = $this->runtime()->get(ComposerLock::class)->composerVersion();

        return $this->component('composer', $local, null, 'unknown', 'composer --version', 'unknown on production');
    }

    /**
     * @param  array<string, mixed>  $server
     * @return array<string, mixed>
     */
    private function database(string $root, array $server): array
    {
        $engine = Arr::get($server, 'database.engine');
        $version = Arr::get($server, 'database.version');
        $production = is_string($engine) ? trim($engine.' '.(is_scalar($version) ? (string) $version : '')) : null;

        $env = $this->runtime()->get(DotEnv::class)->read($root);
        $connection = $env['DB_CONNECTION'] ?? null;

        if ($connection === null || ! is_string($engine)) {
            return $this->component('database', $connection, $production, 'unknown', '.env DB_CONNECTION', 'server database');
        }

        $localEngine = $connection === 'pgsql' ? 'postgres' : $connection;
        $verdict = strcasecmp($localEngine, $engine) === 0 ? 'match' : 'mismatch';

        return $this->component('database', $connection, $production, $verdict, '.env DB_CONNECTION', 'server database');
    }

    /**
     * @return array<string, mixed>
     */
    private function node(): array
    {
        return $this->component('node', $this->runtime()->get(Node::class)->localVersion(), null, 'unknown', 'node -v', 'unknown on production');
    }

    /**
     * @return array<string, mixed>
     */
    private function component(string $name, ?string $local, ?string $production, string $verdict, string $localSource, string $productionSource): array
    {
        return [
            'name' => $name,
            'local' => $local,
            'production' => $production,
            'verdict' => $verdict,
            'level' => match ($verdict) {
                'mismatch' => 'error',
                'warning' => 'warning',
                default => 'info',
            },
            'note' => $verdict === 'unknown' && $production === null ? 'unknown on production' : '',
            'sources' => ['local' => $localSource, 'production' => $productionSource],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $versions
     */
    private function versionOf(array $versions, string $package): ?string
    {
        foreach ($versions as $version) {
            if (($version['name'] ?? null) === $package && is_string($version['installed_version'] ?? null)) {
                // Composer records tags as they are, `v12.64.0`; the comparison wants numbers.
                return ltrim($version['installed_version'], 'v');
            }
        }

        return null;
    }

    private function compareSemver(?string $local, ?string $production): string
    {
        if ($local === null || $production === null) {
            return 'unknown';
        }

        $left = array_map(intval(...), explode('.', $local));
        $right = array_map(intval(...), explode('.', $production));

        if (($left[0] ?? 0) !== ($right[0] ?? 0) || ($left[1] ?? 0) !== ($right[1] ?? 0)) {
            return 'mismatch';
        }

        return ($left[2] ?? 0) === ($right[2] ?? 0) ? 'match' : 'warning';
    }

    /**
     * @param  list<array<string, mixed>>  $mismatches
     * @return list<string>
     */
    private function suggestions(array $mismatches): array
    {
        $suggestions = [];

        foreach ($mismatches as $mismatch) {
            if ($mismatch['name'] === 'php') {
                $suggestions[] = 'Run unolia configure herd to match the production PHP version.';
            }

            if ($mismatch['name'] === 'laravel') {
                $suggestions[] = version_compare((string) ($mismatch['local'] ?? '0'), (string) ($mismatch['production'] ?? '0'), '>')
                    ? 'Local runs a newer Laravel than production. Deploy, or pin composer.json to what production runs.'
                    : 'Run composer update laravel/framework to match production.';
            }
        }

        return array_values(array_unique($suggestions));
    }
}
