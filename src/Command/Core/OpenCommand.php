<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Deployments\ShowDeployment;
use Unolia\Cli\Api\Requests\Projects\ShowProject;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Local\HerdYaml;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Browser;

/**
 * Open the linked project, website, repository or Forge site in the browser.
 */
final class OpenCommand extends BaseCommand
{
    use ResolvesTargets;

    private const TARGETS = ['project', 'website', 'live', 'repo', 'forge', 'deployment'];

    protected function canonical(): string
    {
        return 'open';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Open this project, website, live site, repository or Forge site');
    }

    protected function define(): void
    {
        $this->addArgument('what', InputArgument::OPTIONAL, 'project, website, live, repo, forge or deployment', 'project');
        $this->addArgument('id', InputArgument::OPTIONAL, 'The deployment id, when opening a deployment');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the URL instead of opening it');
    }

    public function examples(): array
    {
        return [
            'The project page' => 'unolia open',
            'The website page' => 'unolia open website',
            'The deployed site itself' => 'unolia open live',
            'The Forge site' => 'unolia open forge --print',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $what = $this->argumentString('what') ?? 'project';

        if (! in_array($what, self::TARGETS, true)) {
            throw CliError::usage(
                sprintf('%s cannot be opened', $what),
                'Open one of: '.implode(', ', self::TARGETS),
                ['candidates' => self::TARGETS],
            );
        }

        $url = $this->urlFor($what);

        if ($this->optionBool('print') || ! $this->ask()->interactive()) {
            $this->printUrl($url);

            return ExitCode::Ok;
        }

        if (! $this->runtime()->get(Browser::class)->open($url, $this->browserOverride())) {
            $this->out()->note('No browser opener on this machine.');
            $this->printUrl($url);

            return ExitCode::Ok;
        }

        $this->out()->info('Opened '.$url);

        return ExitCode::Ok;
    }

    private function urlFor(string $what): string
    {
        return match ($what) {
            'website' => $this->urlOf($this->fetch(new ShowWebsite($this->websiteId())), 'this website'),
            'live' => $this->liveUrl(),
            'repo' => $this->repositoryUrl(),
            'forge' => $this->forgeUrl(),
            'deployment' => $this->deploymentUrl(),
            default => $this->urlOf($this->fetch(new ShowProject($this->context(Need::Project)->requireProject())), 'this project'),
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function urlOf(array $record, string $subject): string
    {
        $url = $record['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw CliError::notFound(sprintf('the API did not give a page for %s', $subject));
        }

        return $url;
    }

    /** The deployed site as visitors reach it, at its primary domain. */
    private function liveUrl(): string
    {
        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $domain = $website['domain'] ?? null;

        if (! is_string($domain) || $domain === '') {
            throw CliError::notFound('this website has no domain yet');
        }

        return 'https://'.$domain;
    }

    private function repositoryUrl(): string
    {
        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $url = Arr::get($website, 'repository.url');

        if (! is_string($url) || $url === '') {
            throw CliError::notFound('this website has no repository');
        }

        return $url;
    }

    private function deploymentUrl(): string
    {
        $id = $this->argumentString('id') ?? $this->local()->get('last_deployment');

        if (! is_scalar($id)) {
            throw CliError::missingInput('the deployment id');
        }

        return $this->urlOf($this->fetch(new ShowDeployment((string) $id)), 'that deployment');
    }

    /**
     * Forge ids live in herd.yml, which is the only place the CLI stores provider ids.
     */
    private function forgeUrl(): string
    {
        $root = $this->runtime()->context()->config()->rootDir()
            ?? $this->runtime()->context()->git()->root()
            ?? $this->runtime()->cwd();

        $document = HerdYaml::read(rtrim($root, '/').'/herd.yml');
        $forge = Arr::get($document, 'integrations.forge');

        if (! is_array($forge) || $forge === []) {
            throw CliError::notFound(
                'herd.yml has no Forge ids',
                'Run unolia configure herd to write them.',
            );
        }

        $entry = reset($forge);
        $server = is_array($entry) ? ($entry['server-id'] ?? null) : null;
        $site = is_array($entry) ? ($entry['site-id'] ?? null) : null;

        if (! is_numeric($server) || ! is_numeric($site)) {
            throw CliError::notFound('herd.yml has no Forge server and site ids');
        }

        return sprintf('https://forge.laravel.com/servers/%d/sites/%d', (int) $server, (int) $site);
    }

    private function browserOverride(): ?string
    {
        $browser = $this->runtime()->settings()->get('browser');

        return is_string($browser) && $browser !== '' ? $browser : null;
    }
}
