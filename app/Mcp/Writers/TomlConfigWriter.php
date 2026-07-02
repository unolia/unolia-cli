<?php

namespace Unolia\UnoliaCLI\Mcp\Writers;

use Illuminate\Support\Facades\File;

/**
 * Adds or replaces a single `[<table>.<server>]` block in a TOML config
 * file (Codex's config.toml). No TOML parsing: an existing block for the
 * same server is removed and a fresh one is appended; everything else in
 * the file stays byte-identical.
 */
class TomlConfigWriter
{
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * @param  array<string, string|bool|int|float|list<string>>  $values
     */
    public function write(string $configKey, string $serverKey, array $values): bool
    {
        $block = $this->buildBlock("{$configKey}.{$serverKey}", $values);

        if (! File::exists($this->path)) {
            File::ensureDirectoryExists(dirname($this->path));
            File::put($this->path, $block);

            return true;
        }

        $contents = $this->removeExistingBlock((string) File::get($this->path), "{$configKey}.{$serverKey}");
        $contents = rtrim($contents);

        File::put($this->path, $contents === '' ? $block : $contents."\n\n".$block);

        return true;
    }

    /**
     * @param  array<string, string|bool|int|float|list<string>>  $values
     */
    private function buildBlock(string $table, array $values): string
    {
        $lines = ["[{$table}]"];

        foreach ($values as $key => $value) {
            $lines[] = "{$key} = ".$this->formatValue($value);
        }

        return implode("\n", $lines)."\n";
    }

    private function formatValue(string|bool|int|float|array $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => '['.implode(', ', array_map($this->formatValue(...), $value)).']',
            default => '"'.addcslashes($value, '"\\').'"',
        };
    }

    /**
     * Strips an existing `[<table>]` block: the header line and everything
     * up to (not including) the next `[` table header or the end of file.
     */
    private function removeExistingBlock(string $contents, string $table): string
    {
        $pattern = '/^\['.preg_quote($table, '/').'\][^\n]*\n?(?:(?!^\[).*\n?)*/m';

        return (string) preg_replace($pattern, '', $contents);
    }
}
