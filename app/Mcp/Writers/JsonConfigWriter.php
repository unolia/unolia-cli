<?php

namespace Unolia\UnoliaCLI\Mcp\Writers;

use Illuminate\Support\Facades\File;
use stdClass;

/**
 * Merges one MCP server entry into a JSON config file without touching
 * anything else in it. Refuses (returns false, file untouched) when the
 * existing content cannot be safely round-tripped - e.g. JSONC comments.
 */
class JsonConfigWriter
{
    /**
     * @param  array<string, mixed>  $newFileBase  top-level keys seeded when creating the file
     */
    public function __construct(
        private readonly string $path,
        private readonly array $newFileBase = [],
    ) {}

    /**
     * @param  string  $configKey  literal top-level key (dots are not treated as nesting)
     * @param  array<string, mixed>  $server
     */
    public function write(string $configKey, string $serverKey, array $server): bool
    {
        $contents = File::exists($this->path) ? trim((string) File::get($this->path)) : '';

        if ($contents === '' || $contents === '{}') {
            $config = (object) $this->newFileBase;
        } else {
            $config = json_decode($contents);

            if (! $config instanceof stdClass) {
                return false;
            }
        }

        $config->{$configKey} ??= new stdClass;

        if (! $config->{$configKey} instanceof stdClass) {
            return false;
        }

        $config->{$configKey}->{$serverKey} = $server;

        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return true;
    }
}
