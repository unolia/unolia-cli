<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

use Unolia\Cli\Console\CliError;

/**
 * .unolia/config.json, the committed file that pins this directory to a team, a project
 * and a website. Found by walking up to the git root, or to the filesystem root.
 */
final class ProjectConfig
{
    public const FILE = '.unolia/config.json';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly ?string $path = null,
        public readonly array $data = [],
    ) {}

    public static function discover(string $cwd, ?string $gitRoot = null): self
    {
        $directory = rtrim($cwd, '/');
        $stop = $gitRoot !== null ? rtrim($gitRoot, '/') : null;

        while ($directory !== '') {
            $candidate = $directory.'/'.self::FILE;

            if (is_file($candidate)) {
                return self::read($candidate);
            }

            if ($stop !== null && $directory === $stop) {
                break;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return new self;
    }

    public static function read(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw CliError::usage(sprintf('could not read %s', $path));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw CliError::usage(
                sprintf('%s is not valid JSON', $path),
                'Fix the file by hand, or run unolia init --force.',
            );
        }

        /** @var array<string, mixed> $decoded */
        return new self($path, $decoded);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string the path written
     */
    public static function write(string $directory, array $data): string
    {
        $dir = rtrim($directory, '/').'/.unolia';

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw CliError::usage(sprintf('could not create %s', $dir));
        }

        $path = $dir.'/config.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw CliError::usage('could not encode the configuration');
        }

        if (@file_put_contents($path, $json."\n") === false) {
            throw CliError::usage(sprintf('could not write %s', $path));
        }

        return $path;
    }

    public function exists(): bool
    {
        return $this->path !== null;
    }

    /** The directory holding .unolia, which is where local.json goes too. */
    public function rootDir(): ?string
    {
        return $this->path === null ? null : dirname($this->path, 2);
    }

    public function team(): ?string
    {
        $team = $this->data['team'] ?? null;

        if (is_string($team) && $team !== '') {
            return $team;
        }

        return is_int($team) ? (string) $team : null;
    }

    public function project(): ?int
    {
        return $this->intValue('project');
    }

    public function website(): ?int
    {
        return $this->intValue('website');
    }

    /**
     * @return array<string, int>
     */
    public function environments(): array
    {
        $environments = $this->data['environments'] ?? null;

        if (! is_array($environments)) {
            return [];
        }

        $map = [];

        foreach ($environments as $name => $website) {
            if (is_string($name) && is_numeric($website)) {
                $map[$name] = (int) $website;
            }
        }

        return $map;
    }

    private function intValue(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        if ($value !== null) {
            throw CliError::usage(
                sprintf('%s in %s must be an id', $key, $this->path ?? self::FILE),
                'Run unolia init --force to write it again.',
            );
        }

        return null;
    }
}
