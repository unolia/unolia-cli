<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

use Closure;
use Saloon\Http\Request;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Core\ListTeams;
use Unolia\Cli\Api\Requests\Core\Resolve;
use Unolia\Cli\Api\Requests\PaginatedRequest;
use Unolia\Cli\Api\Requests\Projects\ListProjects;
use Unolia\Cli\Api\Requests\Websites\ListWebsites;
use Unolia\Cli\Config\Settings;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Arr;

/**
 * Runs once per invocation, most specific answer first:
 * flags, environment, .unolia/config.json, the git remote, the default team, a prompt.
 */
final class ContextResolver
{
    private ?InputInterface $input = null;

    /** @var array<string, Context> */
    private array $cache = [];

    /** @var array<string, mixed>|null */
    private ?array $remoteMatch = null;

    private bool $remoteAsked = false;

    private ?string $team = null;

    private bool $teamResolved = false;

    private ?string $teamSource = null;

    /**
     * @param  array<string, string>  $env
     * @param  Closure(): Client  $client
     */
    public function __construct(
        private readonly array $env,
        private readonly Settings $settings,
        private readonly GitRemote $git,
        private readonly ProjectConfig $config,
        private readonly Ask $ask,
        private readonly Closure $client,
    ) {}

    public function bind(InputInterface $input): void
    {
        $this->input = $input;
        $this->cache = [];
        $this->teamResolved = false;
    }

    public function config(): ProjectConfig
    {
        return $this->config;
    }

    public function git(): GitRemote
    {
        return $this->git;
    }

    public function localState(): LocalState
    {
        return new LocalState($this->config->rootDir() ?? $this->git->root());
    }

    /**
     * @return array<string, int>
     */
    public function environments(): array
    {
        return $this->config->environments();
    }

    /**
     * The team slug for the X-Unolia-Team header. Never calls the API.
     */
    public function teamSlug(): ?string
    {
        if ($this->teamResolved) {
            return $this->team;
        }

        $this->teamResolved = true;

        foreach ($this->teamCandidates() as $source => $value) {
            if (is_string($value) && $value !== '') {
                $this->team = $value;
                $this->teamSource = $source;

                return $this->team;
            }
        }

        return null;
    }

    public function teamSource(): ?string
    {
        $this->teamSlug();

        return $this->teamSource;
    }

    public function resolve(Need $need): Context
    {
        if (isset($this->cache[$need->name])) {
            return $this->cache[$need->name];
        }

        $sources = [];
        $team = $this->teamSlug();

        if ($team !== null && $this->teamSource !== null) {
            $sources['team'] = $this->teamSource;
        }

        [$rawProject, $projectSource] = $this->pick('project', 'UNOLIA_PROJECT', $this->config->project());
        [$rawWebsite, $websiteSource] = $this->pick('website', 'UNOLIA_WEBSITE', $this->config->website());

        // A list run from a checkout is about that checkout: when nothing is
        // configured, the git remote scopes it, quietly. Only a firm need
        // goes on to ask.
        if ($need === Need::None && $rawWebsite === null && $rawProject === null) {
            [$rawWebsite, $rawProject] = $this->fromRemote(null);

            if ($rawWebsite !== null) {
                $websiteSource = 'resolve';
            }

            if ($rawProject !== null) {
                $projectSource = 'resolve';
            }
        }

        if ($need === Need::Website && $rawWebsite === null) {
            [$rawWebsite, $rawProject] = $this->fromRemote($rawProject);

            if ($rawWebsite !== null) {
                $websiteSource = 'resolve';
                $projectSource ??= 'resolve';
            }
        }

        if ($need === Need::Project && $rawProject === null) {
            [$fromRemoteWebsite, $rawProject] = $this->fromRemote($rawProject);

            if ($rawProject !== null) {
                $projectSource = 'resolve';
                $rawWebsite ??= $fromRemoteWebsite;
            }
        }

        if ($need === Need::Website && $rawWebsite === null) {
            $rawWebsite = $this->askForWebsite();
            $websiteSource = 'prompt';
        }

        if ($need === Need::Project && $rawProject === null) {
            $rawProject = $this->askForProject();
            $projectSource = 'prompt';
        }

        if ($need === Need::Team && $team === null) {
            $team = $this->askForTeam();
            $sources['team'] = 'prompt';
        }

        $website = $rawWebsite === null ? null : $this->websiteId($rawWebsite);
        $project = $rawProject === null ? null : $this->projectId($rawProject);

        if ($website !== null && $websiteSource !== null) {
            $sources['website'] = $websiteSource;
        }

        if ($project !== null && $projectSource !== null) {
            $sources['project'] = $projectSource;
        }

        $context = new Context(
            team: $team,
            project: $project,
            website: $website,
            environment: null,
            sources: $sources,
            configPath: $this->config->path,
            gitRoot: $this->git->root(),
            remote: $this->git->remote(),
            branch: $this->git->branch(),
        );

        return $this->cache[$need->name] = $context;
    }

    /** Turn a website id or domain into an id. */
    public function websiteId(int|string $value): int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            return (int) $value;
        }

        $matches = $this->search(new ListWebsites(['q' => $value, 'per_page' => 20]), 'domain', (string) $value);

        return $this->onlyMatch($matches, 'website', (string) $value);
    }

    /** Turn a project id or name into an id. */
    public function projectId(int|string $value): int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            return (int) $value;
        }

        $matches = $this->search(new ListProjects(['q' => $value, 'per_page' => 20]), 'name', (string) $value);

        return $this->onlyMatch($matches, 'project', (string) $value);
    }

    /**
     * The websites the API says this git remote maps to, for prompts and error messages.
     * Another remote than the origin of this directory is asked about once and not kept.
     *
     * @return array<string, mixed>
     */
    public function remoteMatch(?string $remote = null): array
    {
        if ($remote !== null) {
            return $this->lookUp(GitRemote::withoutCredentials($remote));
        }

        if ($this->remoteAsked) {
            return $this->remoteMatch ?? [];
        }

        $this->remoteAsked = true;

        return $this->remoteMatch = $this->lookUp($this->git->remote());
    }

    /**
     * @return array<string, mixed>
     */
    private function lookUp(?string $remote): array
    {
        if ($remote === null) {
            return [];
        }

        try {
            $response = ($this->client)()->send(new Resolve(['remote' => $remote]));
        } catch (ApiException $exception) {
            // An API without the resolve endpoint has nothing to link. Anything
            // else, a rejected token or a host that cannot be reached, is the
            // real answer and must not read as "this directory is not linked".
            if ($exception->status !== 404) {
                throw $exception->toCliError();
            }

            return [];
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remoteWebsites(): array
    {
        $websites = [];

        foreach ($this->remoteTeams() as $team) {
            foreach ((array) ($team['websites'] ?? []) as $website) {
                if (is_array($website)) {
                    $websites[] = $website;
                }
            }
        }

        return $websites;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remoteTeams(): array
    {
        $teams = $this->remoteMatch()['teams'] ?? [];
        $filtered = [];

        foreach ((array) $teams as $team) {
            if (is_array($team)) {
                $filtered[] = $team;
            }
        }

        return $filtered;
    }

    public function option(string $name): ?string
    {
        if ($this->input === null || ! $this->input->hasOption($name)) {
            return null;
        }

        $value = $this->input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function teamCandidates(): array
    {
        return [
            'flag' => $this->option('team'),
            'UNOLIA_TEAM' => $this->env['UNOLIA_TEAM'] ?? null,
            'config' => $this->config->team(),
            'settings' => $this->settings->defaultTeam(),
        ];
    }

    /**
     * @return array{0: string|int|null, 1: string|null}
     */
    private function pick(string $option, string $variable, int|string|null $fromConfig): array
    {
        $flag = $this->option($option);

        if ($flag !== null) {
            return [$flag, 'flag'];
        }

        $fromEnv = $this->env[$variable] ?? null;

        if (is_string($fromEnv) && $fromEnv !== '') {
            return [$fromEnv, $variable];
        }

        if ($fromConfig !== null) {
            return [$fromConfig, 'config'];
        }

        return [null, null];
    }

    /**
     * @return array{0: int|null, 1: string|int|null}
     */
    private function fromRemote(int|string|null $project): array
    {
        $websites = $this->remoteWebsites();

        if (count($websites) === 1) {
            $only = $websites[0];
            $id = $only['id'] ?? null;
            $projectId = $only['project_id'] ?? $project;

            return [is_numeric($id) ? (int) $id : null, is_numeric($projectId) ? (int) $projectId : $project];
        }

        if ($websites === []) {
            foreach ($this->remoteTeams() as $team) {
                $projects = $team['projects'] ?? [];

                if (is_array($projects) && count($projects) === 1) {
                    $id = Arr::get($projects, '0.id');

                    return [null, is_numeric($id) ? (int) $id : $project];
                }
            }
        }

        return [null, $project];
    }

    private function askForWebsite(): int
    {
        $candidates = [];

        foreach ($this->remoteWebsites() as $website) {
            $id = $website['id'] ?? null;
            $domain = $website['domain'] ?? null;

            if (is_numeric($id) && is_string($domain)) {
                $candidates[(string) $id] = $domain;
            }
        }

        if (! $this->ask->interactive()) {
            throw CliError::unlinkedDirectory($candidates, $this->git->remote());
        }

        if ($candidates === []) {
            $candidates = $this->pickList(new ListWebsites(['per_page' => 100]), 'domain');
        }

        if ($candidates === []) {
            throw CliError::unlinkedDirectory([], $this->git->remote());
        }

        return (int) $this->ask->select('Which website?', $candidates, '--website');
    }

    private function askForProject(): int
    {
        $candidates = [];

        foreach ($this->remoteTeams() as $team) {
            foreach ((array) ($team['projects'] ?? []) as $project) {
                if (is_array($project) && is_numeric($project['id'] ?? null) && is_string($project['name'] ?? null)) {
                    $candidates[(string) $project['id']] = $project['name'];
                }
            }
        }

        if ($this->ask->interactive() && $candidates === []) {
            $candidates = $this->pickList(new ListProjects(['per_page' => 100]), 'name');
        }

        if ($candidates === []) {
            throw CliError::missingInput('--project');
        }

        return (int) $this->ask->select('Which project?', $candidates, '--project');
    }

    private function askForTeam(): string
    {
        $teams = [];

        foreach ($this->rows(new ListTeams(['per_page' => 100])) as $team) {
            $slug = $team['slug'] ?? $team['id'] ?? null;
            $name = $team['name'] ?? null;

            if ($slug !== null && is_string($name)) {
                $teams[(string) $slug] = $name;
            }
        }

        if (count($teams) === 1) {
            return (string) array_key_first($teams);
        }

        if ($teams === []) {
            throw CliError::missingInput('--team');
        }

        return $this->ask->select('Which team?', $teams, '--team');
    }

    /**
     * @param  PaginatedRequest  $request
     * @return array<string, string>
     */
    private function pickList(object $request, string $label): array
    {
        $options = [];

        foreach ($this->rows($request) as $row) {
            $id = $row['id'] ?? null;
            $name = $row[$label] ?? null;

            if (is_numeric($id) && is_string($name)) {
                $options[(string) $id] = $name;
            }
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(object $request): array
    {
        try {
            /** @var Request $request */
            $response = ($this->client)()->send($request);
        } catch (ApiException $exception) {
            throw $exception->toCliError();
        }

        $data = $response->json('data');
        $rows = [];

        foreach ((array) (is_array($data) ? $data : []) as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function search(object $request, string $field, string $value): array
    {
        $matches = [];

        foreach ($this->rows($request) as $row) {
            $candidate = $row[$field] ?? null;
            $id = $row['id'] ?? null;

            if (is_string($candidate) && is_numeric($id) && strcasecmp($candidate, $value) === 0) {
                $matches[(int) $id] = $candidate;
            }
        }

        return $matches;
    }

    /**
     * @param  array<int, string>  $matches
     */
    private function onlyMatch(array $matches, string $kind, string $value): int
    {
        if (count($matches) === 1) {
            return (int) array_key_first($matches);
        }

        if ($matches === []) {
            throw CliError::notFound(
                sprintf('no %s called %s', $kind, $value),
                sprintf('Run unolia %s list to see the ones you can reach.', $kind),
            );
        }

        throw CliError::usage(
            sprintf('%s matches several %ss', $value, $kind),
            'Pass the id instead.',
            ['candidates' => $matches],
        );
    }
}
