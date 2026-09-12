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
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Local\HerdYaml;
use Unolia\Cli\Support\Str;

/**
 * Who you are, which team, what this directory maps to, and why. Never prompts.
 */
final class StatusCommand extends BaseCommand
{
    private const EXPIRY_NOTICE_DAYS = 14;

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
                'login' => $login === null ? null : implode(' ', array_map(static fn (Cell $cell): string => $cell->text, $login)),
                'team' => $context->team,
                'project' => $context->project,
                'project_name' => $project,
                'website' => $context->website,
                'website_domain' => $website,
                'git' => $git === null ? null : implode(' ', array_map(static fn (Cell $cell): string => $cell->text, $git)),
                'herd' => $herd,
                'config_path' => $context->configPath,
                'sources' => $sources,
            ]);

            return ExitCode::Ok;
        }

        $rows = [
            ['Host', [Cell::text($host)], ''],
            ['Login', $login ?? self::missing('not logged in, run', 'unolia login'), $this->sourceLabel($sources['login'] ?? null)],
            ['Team', $context->team === null ? self::missing('not set, run', 'unolia team switch <slug>') : [Cell::text($context->team)->bold()], $this->sourceLabel($sources['team'] ?? null)],
            ['Project', self::idAndName($context->project, $project) ?? self::missing('not linked, run', 'unolia init'), $this->sourceLabel($sources['project'] ?? null)],
            ['Website', self::idAndName($context->website, $website) ?? self::missing('not linked, run', 'unolia init'), $this->sourceLabel($sources['website'] ?? null)],
            ['Git', $git ?? [Cell::text('not a git repository')->dim()], $git === null ? '' : 'remote origin'],
            ['Herd', $herd === null ? self::missing('no herd.yml, run', 'unolia configure herd') : [Cell::text($herd)], $herd === null ? '' : 'herd.yml'],
        ];

        $this->out()->formatted($this->align($rows));

        return ExitCode::Ok;
    }

    /**
     * @return list<Cell>
     */
    private function login(): array
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

        $host = $this->runtime()->host();
        $scopes = $token['scopes'] ?? $this->runtime()->hosts()->scopes($host) ?? [];
        $expires = $token['expires_at'] ?? $this->runtime()->hosts()->expiresAt($host);

        $details = sprintf(
            '(%s token, %s',
            Str::scalar($token['tokenable_type'] ?? null, 'unknown'),
            $this->scopeSummary(is_array($scopes) ? $scopes : []),
        );
        $cells = [Cell::text(Str::scalar($principal['name'] ?? null, 'unknown'))->bold()];

        if (! is_string($expires) || $expires === '') {
            return [...$cells, Cell::text($details.')')->dim()];
        }

        $days = $this->warnAboutExpiry($expires);
        $expiry = Cell::text(($days !== null && $days <= 0 ? 'expired ' : 'expires ').substr($expires, 0, 10).')');

        return [...$cells, Cell::text($details.',')->dim(), match (true) {
            $days === null => $expiry->dim(),
            $days <= 0 => $expiry->color('red'),
            $days <= self::EXPIRY_NOTICE_DAYS => $expiry->color('yellow'),
            default => $expiry->dim(),
        }];
    }

    /** Two weeks of notice, so a token does not stop a deploy one morning. Returns the days left. */
    private function warnAboutExpiry(string $expires): ?int
    {
        $timestamp = strtotime($expires);

        if ($timestamp === false) {
            return null;
        }

        $days = (int) ceil(($timestamp - time()) / 86400);

        if ($days <= 0) {
            $this->out()->warn('Your token has expired, run unolia login.');
        } elseif ($days <= self::EXPIRY_NOTICE_DAYS) {
            $this->out()->warn(sprintf('Your token expires in %d day%s, run unolia login.', $days, $days === 1 ? '' : 's'));
        }

        return $days;
    }

    /**
     * Short enough for one line: the list when it is short, a count when it is not.
     *
     * @param  array<mixed>  $scopes
     */
    private function scopeSummary(array $scopes): string
    {
        $names = array_values(array_filter(array_map(static fn (mixed $scope): string => Str::scalar($scope, ''), $scopes), static fn (string $scope): bool => $scope !== ''));

        return match (true) {
            $names === [] => 'scopes -',
            count($names) <= 4 => 'scopes '.implode(' ', $names),
            default => count($names).' scopes',
        };
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

    /**
     * @return list<Cell>|null
     */
    private static function idAndName(?int $id, ?string $name): ?array
    {
        if ($id === null) {
            return null;
        }

        $cells = [Cell::text('#'.$id)->dim()];

        return $name === null ? $cells : [...$cells, Cell::text($name)];
    }

    /**
     * Something missing and the command that sets it: the words amber, the command cyan.
     *
     * @return list<Cell>
     */
    private static function missing(string $words, string $command): array
    {
        return [Cell::text($words)->color('yellow'), Cell::text($command)->color('cyan')];
    }

    /**
     * @return list<Cell>|null
     */
    private function gitLine(): ?array
    {
        $git = $this->runtime()->context()->git();
        $slug = $git->slug();

        if ($slug === null) {
            return null;
        }

        $cells = [Cell::text($slug)];
        $branch = $git->branch();

        if ($branch !== null) {
            $cells[] = Cell::text('@ '.$branch);
        }

        $commit = $git->commit();

        if ($commit !== null) {
            $cells[] = Cell::text('('.$commit.')')->dim();
        }

        return $cells;
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
     * Label, value, source in three aligned columns. The value is a list of
     * cells joined by spaces, measured on their plain text so colour never
     * moves a column; the source, the least important, is dim.
     *
     * @param  list<array{0: string, 1: list<Cell>, 2: string}>  $rows
     */
    private function align(array $rows): string
    {
        $labelWidth = 0;
        $valueWidth = 0;

        foreach ($rows as [$label, $value]) {
            $labelWidth = max($labelWidth, mb_strwidth($label));
            $valueWidth = max($valueWidth, self::width($value));
        }

        $lines = [];

        foreach ($rows as [$label, $value, $source]) {
            $styled = implode(' ', array_map(static fn (Cell $cell): string => $cell->styled(false), $value));
            $line = str_pad($label, $labelWidth + 2).$styled;

            if ($source !== '') {
                $line .= str_repeat(' ', $valueWidth - self::width($value) + 2).'<fg=gray>'.$source.'</>';
            }

            $lines[] = rtrim($line);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<Cell>  $cells
     */
    private static function width(array $cells): int
    {
        $width = 0;

        foreach ($cells as $cell) {
            $width += $cell->width();
        }

        return $width + max(0, count($cells) - 1);
    }
}
