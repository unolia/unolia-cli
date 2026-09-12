<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Local;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Servers\ShowServer;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Api\Requests\Websites\WebsiteDomains;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Local\Herd;
use Unolia\Cli\Local\HerdPlanInput;
use Unolia\Cli\Local\HerdYaml;
use Unolia\Cli\Support\Arr;

/**
 * Write herd.yml from what production actually runs, then let Herd apply it.
 */
final class ConfigureHerdCommand extends BaseCommand
{
    use ResolvesTargets;

    /** @var list<array<string, mixed>>|null */
    private ?array $domains = null;

    protected function canonical(): string
    {
        return 'configure:herd';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Write herd.yml from production and run herd init');
    }

    protected function define(): void
    {
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the YAML and do nothing else');
        $this->addOption('no-services', null, InputOption::VALUE_NONE, 'Leave the services block out');
        $this->addOption('no-yml', null, InputOption::VALUE_NONE, 'Run herd directly and write nothing');
        $this->addOption('no-init', null, InputOption::VALUE_NONE, 'Write herd.yml but do not run herd init');
        $this->addOption('site', null, InputOption::VALUE_REQUIRED, 'Herd site name, the directory name by default');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Match production' => 'unolia configure herd',
            'See the plan first' => 'unolia configure herd --dry-run',
            'Only the YAML' => 'unolia configure herd --print',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $herd = $this->runtime()->get(Herd::class);
        $root = $this->projectRoot();
        $path = $root.'/herd.yml';

        // Nothing is asked of the API before the one thing that can only be
        // answered locally: without Herd there is nothing to configure.
        if (! $herd->isInstalled() && ! $this->optionBool('print')) {
            throw CliError::usage(
                'Herd is not installed',
                'Install it from https://herd.laravel.com, or use --print.',
            );
        }

        $website = $this->fetch(new ShowWebsite($this->websiteId()));
        $php = is_string($website['php_version'] ?? null) ? $website['php_version'] : null;
        $serverId = Arr::get($website, 'managed_server.id');

        if ($php === null && ! is_numeric($serverId)) {
            throw CliError::notFound(
                'this website has no PHP version and no managed server, so there is nothing to configure',
            );
        }

        $server = is_numeric($serverId) ? $this->fetch(new ShowServer((int) $serverId)) : [];

        $existing = HerdYaml::read($path);
        $desired = HerdYaml::build($this->planInput($website, $server, $existing, $herd, $root));
        $merged = HerdYaml::merge($existing, $desired);
        $yaml = HerdYaml::dump($merged);

        if ($this->optionBool('print')) {
            $this->out()->raw($yaml);

            return ExitCode::Ok;
        }

        if ($this->optionBool('no-yml')) {
            return $this->withoutYaml($herd, $root, $merged);
        }

        $diff = HerdYaml::diff($existing, $merged);
        $changes = $existing === [] || $diff !== [];
        $plan = $this->plan($path, $diff, $existing === []);

        if ($this->dryRun()) {
            $this->report($merged, $plan, $yaml, $diff, [], true, false);

            return ExitCode::Ok;
        }

        if (! $changes) {
            $this->out()->note('herd.yml already matches production');

            if ($this->optionBool('no-init')) {
                return ExitCode::Ok;
            }
        }

        // Herd is asked to touch the machine either way (isolate, link,
        // secure), so a matching file is not a reason to skip the question: a
        // pipe without --yes is refused here exactly as it is when the file
        // changes.
        $question = $changes ? 'Write herd.yml and run herd init?' : 'Run herd init to apply herd.yml?';

        if (! $this->confirmOrPlan($question)) {
            $this->out()->note($changes ? 'Nothing was written.' : 'Nothing was run.');

            return ExitCode::Ok;
        }

        if ($changes) {
            $this->runtime()->paths()->writeAtomic($path, $yaml, 0644);
        }

        $applied = $this->apply($herd, $root, $merged);

        $this->report($merged, $plan, $yaml, $diff, $applied, false, $changes);

        return $applied === [] || ! in_array('failed', $applied, true) ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    private function projectRoot(): string
    {
        return $this->runtime()->context()->config()->rootDir()
            ?? $this->runtime()->context()->git()->root()
            ?? $this->runtime()->cwd();
    }

    /**
     * @param  array<string, mixed>  $website
     * @param  array<string, mixed>  $server
     * @param  array<string, mixed>  $existing
     */
    private function planInput(array $website, array $server, array $existing, Herd $herd, string $root): HerdPlanInput
    {
        $name = $this->optionString('site')
            ?? (is_string($existing['name'] ?? null) ? $existing['name'] : null)
            ?? $herd->siteName($root);

        $php = is_string($website['php_version'] ?? null)
            ? $website['php_version']
            : (is_string(Arr::get($server, 'php_version')) ? (string) Arr::get($server, 'php_version') : null);

        $forgeDomain = null;
        $forgeSite = null;
        $forgeServer = null;

        if (Arr::get($website, 'provider.slug') === 'forge') {
            $forgeDomain = is_string($website['domain'] ?? null) ? $website['domain'] : null;
            $forgeSite = Arr::get($website, 'provider.identifier');
            $forgeServer = Arr::get($website, 'managed_server.identifier');
        }

        return new HerdPlanInput(
            name: $name,
            php: $php,
            secured: $this->secured($existing),
            aliases: $this->aliases($existing, $name, is_string($website['domain'] ?? null) ? $website['domain'] : null),
            databaseEngine: is_string(Arr::get($server, 'database.engine')) ? (string) Arr::get($server, 'database.engine') : null,
            databaseVersion: is_scalar(Arr::get($server, 'database.version')) ? (string) Arr::get($server, 'database.version') : null,
            withServices: ! $this->optionBool('no-services') && $herd->isPro(),
            forgeDomain: $forgeDomain,
            forgeServerId: is_numeric($forgeServer) ? (int) $forgeServer : null,
            forgeSiteId: is_numeric($forgeSite) ? (int) $forgeSite : null,
        );
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    private function secured(array $existing): bool
    {
        foreach ($this->domains() as $domain) {
            $status = $domain['ssl_status'] ?? null;

            if (is_string($status) && $status !== 'none') {
                return true;
            }
        }

        return is_bool($existing['secured'] ?? null) ? (bool) $existing['secured'] : true;
    }

    /**
     * Aliases create local hostnames, so they are only added when someone says yes.
     * The website's alias domains (ws.example.com next to example.com) become
     * Herd aliases by their first label, so ws.example.com answers as ws.test.
     *
     * @param  array<string, mixed>  $existing
     * @return list<string>
     */
    private function aliases(array $existing, string $site, ?string $primary): array
    {
        $current = [];

        foreach (is_array($existing['aliases'] ?? null) ? $existing['aliases'] : [] as $alias) {
            if (is_string($alias)) {
                $current[] = $alias;
            }
        }

        if (! $this->ask()->interactive()) {
            return $current;
        }

        $extra = [];

        foreach ($this->domains() as $domain) {
            if (($domain['kind'] ?? null) !== 'alias' || ! is_string($domain['domain'] ?? null)) {
                continue;
            }

            $label = explode('.', $domain['domain'])[0];

            if ($label !== '' && ! in_array($label, $current, true) && ! isset($extra[$label])) {
                $extra[$label] = $domain['domain'];
            }
        }

        if ($extra === []) {
            return $current;
        }

        $local = array_map(static fn (string $label): string => $label.'.test', array_keys($extra));

        $this->out()->note(sprintf(
            '%s also answers on %s.',
            $primary ?? 'The website',
            implode(', ', $extra),
        ));

        $question = sprintf(
            'Add %s as a local alias of %s.test?',
            implode(', ', $local),
            $site,
        );

        return $this->ask()->confirm($question, false)
            ? array_merge($current, array_keys($extra))
            : $current;
    }

    /**
     * The domains of the website, read once.
     *
     * @return list<array<string, mixed>>
     */
    private function domains(): array
    {
        return $this->domains ??= $this->collection(new WebsiteDomains($this->websiteId(), ['per_page' => 50]));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $diff
     * @return list<array<string, mixed>>
     */
    private function plan(string $path, array $diff, bool $creating): array
    {
        $plan = [['write' => $path, 'change' => $creating ? 'create' : ($diff === [] ? 'none' : 'update')]];

        if (! $this->optionBool('no-init')) {
            $plan[] = ['run' => 'herd init -n', 'effects' => 'install and isolate PHP, link and secure the site'];
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function apply(Herd $herd, string $root, array $document): array
    {
        $applied = [];
        $php = is_scalar($document['php'] ?? null) ? (string) $document['php'] : null;

        if (! $this->optionBool('no-init')) {
            $result = $herd->init($root);
            $applied[] = $result->successful() ? 'herd init' : 'failed';

            if (! $result->successful() && $result->ran) {
                $this->out()->warn(trim($result->error !== '' ? $result->error : $result->output));
            }
        }

        if ($php !== null && $herd->isolatedVersion($root) !== $php) {
            $herd->isolate($root, $php);
            $herd->link($root);
            $applied[] = 'herd isolate '.$php;
            $applied[] = 'herd link';
        }

        $name = is_string($document['name'] ?? null) ? $document['name'] : null;

        if (($document['secured'] ?? false) === true && $name !== null && ! in_array($name, $herd->securedSites(), true)) {
            $herd->secure($name);
            $applied[] = 'herd secure '.$name;
        }

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function withoutYaml(Herd $herd, string $root, array $document): ExitCode
    {
        $php = is_scalar($document['php'] ?? null) ? (string) $document['php'] : null;

        if ($this->dryRun()) {
            $this->out()->record(['plan' => 'herd isolate '.(string) $php.', herd link, herd secure', 'dry_run' => true]);

            return ExitCode::Ok;
        }

        if (! $this->confirmOrPlan('Run herd isolate, link and secure here?')) {
            $this->out()->note('Nothing was run.');

            return ExitCode::Ok;
        }

        if ($php !== null) {
            $herd->isolate($root, $php);
        }

        $herd->link($root);

        $name = is_string($document['name'] ?? null) ? $document['name'] : null;

        if ($name !== null) {
            $herd->secure($name);
        }

        $this->out()->note('The Forge ids were not stored, because that needs herd.yml.');

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $plan
     * @param  list<array{0: string, 1: string}>  $diff
     * @param  list<string>  $applied
     * @param  bool  $wrote  whether herd.yml was actually written this run
     */
    private function report(array $document, array $plan, string $yaml, array $diff, array $applied, bool $dryRun, bool $wrote): void
    {
        if ($this->structured()) {
            $this->out()->record([
                'herd_yml' => $document,
                'plan' => $plan,
                'diff' => array_map(static fn (array $line): string => $line[0].' '.$line[1], $diff),
                'applied' => $applied,
                'wrote' => $wrote,
                'dry_run' => $dryRun,
            ]);

            return;
        }

        if ($diff !== []) {
            $this->out()->line('');

            foreach ($diff as [$sign, $line]) {
                $this->out()->line($sign.' '.$line);
            }
        }

        if ($dryRun) {
            $this->out()->line('');
            $this->out()->line($yaml);

            return;
        }

        foreach ($applied as $step) {
            $this->out()->info($step);
        }

        if ($wrote) {
            $this->out()->info('Wrote herd.yml');
        }
    }
}
