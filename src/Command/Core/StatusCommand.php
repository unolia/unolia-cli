<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Core\CurrentAuthenticated;
use Unolia\Cli\Api\Requests\Core\CurrentToken;
use Unolia\Cli\Api\Requests\Projects\ShowProject;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Local\HerdYaml;
use Unolia\Cli\Support\Str;

/**
 * Who you are, which team, what this directory maps to, and why. Never prompts.
 */
final class StatusCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'status';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Who you are, which team, and what this directory maps to');
    }

    public function examples(): array
    {
        return [
            'Where am I' => 'unolia status',
            'In a script' => 'unolia status --json website,team',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->runtime()->host();
        $context = $this->context(Need::None);
        $sources = $context->sources;

        $login = null;
        $loginSource = $this->runtime()->hosts()->tokenSource($host);

        if ($this->runtime()->hosts()->tokenFor($host) !== null) {
            $login = $this->login();
            $sources['login'] = $loginSource ?? 'hosts.json';
        }

        $project = $this->name('project', $context->project);
        $website = $this->name('website', $context->website);
        $git = $this->gitLine();
        $herd = $this->herdLine();

        if ($this->structured()) {
            $this->out()->record([
                'host' => $host,
                'login' => $login,
                'team' => $context->team,
                'project' => $context->project,
                'project_name' => $project,
                'website' => $context->website,
                'website_domain' => $website,
                'git' => $git,
                'herd' => $herd,
                'config_path' => $context->configPath,
                'sources' => $sources,
            ]);

            return ExitCode::Ok;
        }

        $rows = [
            ['Host', $host, ''],
            ['Login', $login ?? 'not logged in, run unolia login', $this->sourceLabel($sources['login'] ?? null)],
            ['Team', $context->team ?? 'not set, run unolia team switch <slug>', $this->sourceLabel($sources['team'] ?? null)],
            ['Project', $this->idAndName($context->project, $project) ?? 'not linked, run unolia init', $this->sourceLabel($sources['project'] ?? null)],
            ['Website', $this->idAndName($context->website, $website) ?? 'not linked, run unolia init', $this->sourceLabel($sources['website'] ?? null)],
            ['Git', $git ?? 'not a git repository', $git === null ? '' : 'remote origin'],
            ['Herd', $herd ?? 'no herd.yml, run unolia configure herd', $herd === null ? '' : 'herd.yml'],
        ];

        $this->out()->line($this->align($rows));

        return ExitCode::Ok;
    }

    private function login(): string
    {
        try {
            $token = $this->fetch(new CurrentToken);
            $principal = $this->fetch(new CurrentAuthenticated);
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                throw CliError::auth('your token was rejected', 'Run unolia login');
            }

            throw $exception;
        }

        $scopes = $token['scopes'] ?? [];
        $scopes = is_array($scopes) ? implode(' ', array_map(strval(...), $scopes)) : '*';

        return sprintf(
            '%s (%s token, scopes %s)',
            Str::scalar($principal['name'] ?? null, 'unknown'),
            Str::scalar($token['tokenable_type'] ?? null, 'unknown'),
            $scopes === '' ? '-' : $scopes,
        );
    }

    private function name(string $kind, ?int $id): ?string
    {
        if ($id === null || $this->runtime()->hosts()->tokenFor($this->runtime()->host()) === null) {
            return null;
        }

        try {
            $record = $kind === 'project'
                ? $this->fetch(new ShowProject($id))
                : $this->fetch(new ShowWebsite($id));
        } catch (ApiException) {
            return null;
        }

        $value = $kind === 'project' ? ($record['name'] ?? null) : ($record['domain'] ?? null);

        return is_string($value) ? $value : null;
    }

    /** Where a value came from, written the way a person would say it. */
    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            null => '',
            'flag' => 'command line',
            'config' => ProjectConfig::FILE,
            'settings' => 'config.json',
            'resolve' => 'git remote',
            'prompt' => 'you, just now',
            default => $source,
        };
    }

    private function idAndName(?int $id, ?string $name): ?string
    {
        if ($id === null) {
            return null;
        }

        return $name === null ? (string) $id : $id.' '.$name;
    }

    private function gitLine(): ?string
    {
        $git = $this->runtime()->context()->git();
        $slug = $git->slug();

        if ($slug === null) {
            return null;
        }

        $line = $slug;
        $branch = $git->branch();

        if ($branch !== null) {
            $line .= ' @ '.$branch;
        }

        $commit = $git->commit();

        if ($commit !== null) {
            $line .= ' ('.$commit.')';
        }

        return $line;
    }

    private function herdLine(): ?string
    {
        $root = $this->runtime()->context()->config()->rootDir()
            ?? $this->runtime()->context()->git()->root()
            ?? $this->runtime()->cwd();

        $document = HerdYaml::read(rtrim($root, '/').'/herd.yml');

        if ($document === []) {
            return null;
        }

        $parts = [];
        $name = $document['name'] ?? null;

        if (is_string($name)) {
            $parts[] = $name.'.test';
        }

        $php = $document['php'] ?? null;

        if (is_scalar($php)) {
            $parts[] = 'PHP '.$php;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     */
    private function align(array $rows): string
    {
        $labelWidth = 0;
        $valueWidth = 0;

        foreach ($rows as [$label, $value, $source]) {
            $labelWidth = max($labelWidth, mb_strwidth($label));
            $valueWidth = max($valueWidth, mb_strwidth($value));
        }

        $lines = [];

        foreach ($rows as [$label, $value, $source]) {
            $line = str_pad($label, $labelWidth + 2).str_pad($value, $source === '' ? 0 : $valueWidth + 2).$source;
            $lines[] = rtrim($line);
        }

        return implode("\n", $lines);
    }
}
