<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A throwaway home directory and working directory, so a test never touches the real one.
 */
final class TempHome
{
    /** @var list<self> every home created in this test run, torn down together */
    private static array $created = [];

    public readonly string $root;

    public readonly string $home;

    public readonly string $cwd;

    public function __construct()
    {
        $this->root = sys_get_temp_dir().'/unolia-cli-'.bin2hex(random_bytes(6));
        $this->home = $this->root.'/home';
        $this->cwd = $this->root.'/work';

        mkdir($this->home, 0700, true);
        mkdir($this->cwd, 0755, true);

        self::$created[] = $this;
    }

    public static function destroyAll(): void
    {
        foreach (self::$created as $home) {
            $home->destroy();
        }

        self::$created = [];
    }

    public function path(string $relative): string
    {
        return $this->cwd.'/'.ltrim($relative, '/');
    }

    public function write(string $relative, string $contents): string
    {
        $path = $this->path($relative);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    public function read(string $relative): ?string
    {
        $path = $this->path($relative);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * @return array<mixed>|null
     */
    public function readJson(string $relative): ?array
    {
        $contents = $this->read($relative);

        if ($contents === null) {
            return null;
        }

        /** @var array<mixed> $decoded */
        $decoded = json_decode($contents, true);

        return $decoded;
    }

    public function homePath(string $relative): string
    {
        return $this->home.'/'.ltrim($relative, '/');
    }

    public function destroy(): void
    {
        $this->remove($this->root);
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path.'/'.$entry);
            }
        }

        @rmdir($path);
    }
}
