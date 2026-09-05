<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

/**
 * .unolia/local.json, gitignored. Only what a bare `unolia watch` needs.
 */
final class LocalState
{
    public const FILE = '.unolia/local.json';

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly ?string $rootDir) {}

    public function path(): ?string
    {
        return $this->rootDir === null ? null : rtrim($this->rootDir, '/').'/'.self::FILE;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = $this->path();

        if ($path === null || ! is_file($path)) {
            return $this->data = [];
        }

        $contents = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $contents === false ? null : json_decode($contents, true);

        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        return $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function remember(string $key, mixed $value): void
    {
        $path = $this->path();

        if ($path === null) {
            return;
        }

        $data = $this->all();
        $data[$key] = $value;
        $data[$key.'_at'] = gmdate('c');
        $data['resolved_at'] = gmdate('c');
        $this->data = $data;

        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json !== false) {
            @file_put_contents($path, $json."\n");
        }
    }
}
