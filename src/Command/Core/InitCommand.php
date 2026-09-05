<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Support\Arr;

/**
 * Link this directory to a project and a website, and write .unolia/config.json.
 */
final class InitCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'init';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Link this directory to a project and website');
    }

    protected function define(): void
    {
        $this->addOption('environment', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extra name=website mapping');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing configuration');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Link this directory' => 'unolia init',
            'Without a terminal' => 'unolia init --website 118',
            'With an extra environment' => 'unolia init --website 118 --environment staging=121',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $resolver = $this->runtime()->context();
        $existing = $resolver->config();

        if ($existing->exists() && ! $this->optionBool('force')) {
            throw CliError::usage(
                sprintf('%s already exists', (string) $existing->path),
                'Run unolia init --force to write it again.',
            );
        }

        $root = $resolver->git()->root() ?? $this->runtime()->cwd();
        $websiteId = $this->chooseWebsite();
        $website = $this->website($websiteId);

        $team = Arr::get($website, 'project.team.slug') ?? $resolver->teamSlug() ?? $this->teamOfWebsite($websiteId);
        $project = Arr::get($website, 'project.id');

        $config = array_filter([
            'team' => is_string($team) ? $team : null,
            'project' => is_numeric($project) ? (int) $project : null,
            'website' => $websiteId,
            'environments' => $this->environments(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($this->dryRun()) {
            $this->out()->record($config + ['path' => $root.'/'.ProjectConfig::FILE]);

            return ExitCode::Ok;
        }

        $path = ProjectConfig::write($root, $config);
        $ignored = $this->ignoreLocalState($root);

        if ($this->structured()) {
            $this->out()->record($config + ['path' => $path, 'gitignore_updated' => $ignored]);

            return ExitCode::Ok;
        }

        $environments = array_keys($this->environments());

        $this->out()->info(sprintf(
            'Wrote %s (team %s, project %s, website %d%s)',
            ProjectConfig::FILE,
            (string) ($config['team'] ?? 'unknown'),
            (string) ($config['project'] ?? 'unknown'),
            $websiteId,
            $environments === [] ? '' : ', environments '.implode(', ', $environments),
        ));

        if ($ignored) {
            $this->out()->note('Added .unolia/local.json to .gitignore');
        }

        return ExitCode::Ok;
    }

    /**
     * The website record. A 404 here is nearly always the right id in the
     * wrong team: the request carried the team this directory or your
     * settings name, and the website lives in another one you belong to.
     *
     * @return array<string, mixed>
     */
    private function website(int $websiteId): array
    {
        try {
            return $this->fetch(new ShowWebsite($websiteId));
        } catch (ApiException $exception) {
            if ($exception->status !== 404) {
                throw $exception;
            }

            $team = $this->runtime()->context()->teamSlug();

            throw CliError::notFound(
                $team === null
                    ? sprintf('no website %d in your default team', $websiteId)
                    : sprintf('no website %d in team %s', $websiteId, $team),
                'Pass --team <slug> when the website belongs to another team, or run unolia website list to find it.',
            );
        }
    }

    private function chooseWebsite(): int
    {
        $flag = $this->optionString('website');

        if ($flag !== null) {
            return $this->runtime()->context()->websiteId($flag);
        }

        $candidates = [];

        foreach ($this->runtime()->context()->remoteWebsites() as $website) {
            if (is_numeric($website['id'] ?? null) && is_string($website['domain'] ?? null)) {
                $candidates[(string) $website['id']] = $this->label($website);
            }
        }

        if (count($candidates) === 1 && $this->confidence() === 'exact') {
            return (int) array_key_first($candidates);
        }

        if ($candidates === []) {
            throw CliError::unlinkedDirectory([], $this->runtime()->context()->git()->remote());
        }

        return (int) $this->ask()->select('Which website is this directory?', $candidates, '--website');
    }

    private function confidence(): string
    {
        $confidence = $this->runtime()->context()->remoteMatch()['confidence'] ?? 'none';

        return is_string($confidence) ? $confidence : 'none';
    }

    /**
     * @param  array<string, mixed>  $website
     */
    private function label(array $website): string
    {
        $environment = $website['environment'] ?? null;

        return is_string($environment) && $environment !== ''
            ? sprintf('%s (%s)', (string) $website['domain'], $environment)
            : (string) $website['domain'];
    }

    /**
     * The environments map: every website of this repository, plus anything --environment added.
     *
     * @return array<string, int>
     */
    private function environments(): array
    {
        $map = [];

        foreach ($this->runtime()->context()->remoteWebsites() as $website) {
            $id = $website['id'] ?? null;
            $name = $website['environment'] ?? $website['branch'] ?? null;

            if (is_numeric($id) && is_string($name) && $name !== '') {
                $map[$name] = (int) $id;
            }
        }

        foreach ($this->optionList('environment') as $pair) {
            if (! str_contains($pair, '=')) {
                throw CliError::usage(sprintf('--environment %s is not a name=website pair', $pair));
            }

            [$name, $value] = explode('=', $pair, 2);
            $map[trim($name)] = $this->runtime()->context()->websiteId(trim($value));
        }

        return $map;
    }

    private function teamOfWebsite(int $websiteId): ?string
    {
        foreach ($this->runtime()->context()->remoteTeams() as $team) {
            foreach ((array) ($team['websites'] ?? []) as $website) {
                if (is_array($website) && (int) ($website['id'] ?? 0) === $websiteId) {
                    $slug = Arr::get($team, 'team.slug');

                    return is_string($slug) ? $slug : null;
                }
            }
        }

        return null;
    }

    /**
     * local.json holds ids of things this directory started, so it stays out of
     * git. Says whether .gitignore was touched, so the command can say so too.
     */
    private function ignoreLocalState(string $root): bool
    {
        $path = rtrim($root, '/').'/.gitignore';

        if (! file_exists(rtrim($root, '/').'/.git') && ! is_file($path)) {
            return false;
        }

        $contents = is_file($path) ? (string) @file_get_contents($path) : '';

        if (str_contains($contents, '.unolia/local.json')) {
            return false;
        }

        $line = ($contents === '' || str_ends_with($contents, "\n") ? '' : "\n").".unolia/local.json\n";

        return @file_put_contents($path, $line, FILE_APPEND) !== false;
    }
}
