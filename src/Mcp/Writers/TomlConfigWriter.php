<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp\Writers;

/**
 * Adds or replaces a single `[<table>.<server>]` block in a TOML document, the shape of
 * Codex's config.toml. No TOML parser: an existing block for the same server is removed
 * and a fresh one appended, everything else stays byte for byte.
 */
final class TomlConfigWriter
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function merge(string $contents, string $configKey, string $serverKey, array $values): string
    {
        $table = $configKey.'.'.$serverKey;
        $block = self::block($table, $values);
        $contents = rtrim(self::withoutBlock($contents, $table));

        return $contents === '' ? $block : $contents."\n\n".$block;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function block(string $table, array $values): string
    {
        $lines = ['['.$table.']'];

        foreach ($values as $key => $value) {
            $lines[] = $key.' = '.self::format($value);
        }

        return implode("\n", $lines)."\n";
    }

    private static function format(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return '['.implode(', ', array_map(self::format(...), array_values($value))).']';
        }

        return '"'.addcslashes(is_scalar($value) ? (string) $value : '', '"\\').'"';
    }

    /**
     * Strips an existing `[<table>]` block: its header and everything up to the next
     * table header or the end of the document.
     */
    private static function withoutBlock(string $contents, string $table): string
    {
        $pattern = '/^\['.preg_quote($table, '/').'\][^\n]*\n?(?:(?!^\[).*\n?)*/m';

        return (string) preg_replace($pattern, '', $contents);
    }
}
