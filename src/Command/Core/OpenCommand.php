<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Deployments\ShowDeployment;
use Unolia\Cli\Api\Requests\Domains\ShowDomain;
use Unolia\Cli\Api\Requests\Projects\ShowProject;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Browser;

/**
 * Open the linked project, website, repository or Forge site in the browser.
 */
final class OpenCommand extends BaseCommand
{
    use ResolvesTargets;
    use ResolvesZones;

    private const TARGETS = ['project', 'website', 'live', 'repo', 'deployment', 'domain', 'dns', 'forge', 'ploi', 'cloud', 'ovh', 'pages', 'github', 'gitlab'];

    /** A hosting target and the provider slug the API reports for it. */
    private const HOSTS = ['forge' => 'forge', 'ploi' => 'ploi', 'cloud' => 'laravel-cloud', 'ovh' => 'ovh', 'pages' => 'github'];

    /** A code host target and the repository source the API reports for it. */
    private const CODE_HOSTS = ['github' => 'github', 'gitlab' => 'gitlab'];

    protected function canonical(): string
    {
        return 'open';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Open this project, website, live site, repository, or the site at its provider');
    }

    protected function define(): void
    {
        $this->addArgument('what', InputArgument::OPTIONAL, 'project, website, live, repo, deployment, domain, or a provider: forge, ploi, cloud, ovh, pages, github, gitlab', 'project');
        $this->addArgument('id', InputArgument::OPTIONAL, 'The deployment id, or the zone when opening a domain');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the URL instead of opening it');
    }

    public function examples(): array
    {
        return [
            'The project page' => 'unolia open',
            'The website page' => 'unolia open website',
            'The deployed site itself' => 'unolia open live',
            'The site at its host' => 'unolia open forge',
            'The repository at GitHub, only the URL' => 'unolia open github --print',
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
            'deployment' => $this->deploymentUrl(),
            'domain', 'dns' => $this->urlOf($this->fetch(new ShowDomain($this->zone($this->argumentString('id')))), 'this zone'),
            'forge', 'ploi', 'cloud', 'ovh', 'pages' => $this->hostUrl($what),
            'github', 'gitlab' => $this->codeHostUrl($what),
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
     * The site at its hosting provider, only when that is where it lives: asking
     * for Forge about a Ploi site is a wrong turn, not a page.
     */
    private function hostUrl(string $what): string
    {
        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $slug = Arr::get($website, 'provider.slug');
        $label = Arr::get($website, 'provider.label');

        if (! is_string($slug) || $slug === '') {
            throw CliError::notFound('this website has no hosting provider');
        }

        if ($slug !== self::HOSTS[$what]) {
            $target = array_search($slug, self::HOSTS, true);

            throw CliError::notFound(
                sprintf('this website is hosted on %s, not %s', is_string($label) ? $label : $slug, self::labelOf($what)),
                is_string($target) ? sprintf('Run unolia open %s.', $target) : null,
            );
        }

        $url = Arr::get($website, 'provider.url');

        if (! is_string($url) || $url === '') {
            throw CliError::notFound(sprintf('no page is known for this website at %s', self::labelOf($what)));
        }

        return $url;
    }

    /** The repository at its code host, only when that is where it lives. */
    private function codeHostUrl(string $what): string
    {
        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $source = Arr::get($website, 'repository.source');

        if (! is_array($website['repository'] ?? null)) {
            throw CliError::notFound('this website has no repository');
        }

        if ($source !== self::CODE_HOSTS[$what]) {
            $target = is_string($source) ? array_search($source, self::CODE_HOSTS, true) : false;

            throw CliError::notFound(
                sprintf('the repository of this website is on %s, not %s', is_string($source) ? self::labelOf($source) : 'another host', self::labelOf($what)),
                is_string($target) ? sprintf('Run unolia open %s.', $target) : 'Run unolia open repo to go wherever it is.',
            );
        }

        $url = Arr::get($website, 'repository.url');

        if (! is_string($url) || $url === '') {
            throw CliError::notFound('the API did not give a page for this repository');
        }

        return $url;
    }

    private static function labelOf(string $target): string
    {
        return match ($target) {
            'forge' => 'Forge',
            'ploi' => 'Ploi',
            'cloud', 'laravel-cloud' => 'Laravel Cloud',
            'ovh' => 'OVH',
            'pages' => 'GitHub Pages',
            'github' => 'GitHub',
            'gitlab' => 'GitLab',
            default => ucfirst($target),
        };
    }

    private function browserOverride(): ?string
    {
        $browser = $this->runtime()->settings()->get('browser');

        return is_string($browser) && $browser !== '' ? $browser : null;
    }
}
