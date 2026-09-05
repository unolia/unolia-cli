<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp\Writers;

use stdClass;

/**
 * Merges one MCP server entry into a JSON config document without touching anything
 * else in it. Answers null, and the caller leaves the file alone, when the existing
 * content cannot be round tripped safely, JSONC comments for instance.
 */
final class JsonConfigWriter
{
    /**
     * @param  array<string, mixed>  $newFileBase  top level keys seeded when the document is empty
     * @param  string  $configKey  literal top level key, a dot is not nesting
     * @param  array<string, mixed>  $server
     */
    public static function merge(string $contents, array $newFileBase, string $configKey, string $serverKey, array $server): ?string
    {
        $contents = trim($contents);

        if ($contents === '' || $contents === '{}') {
            $config = (object) $newFileBase;
        } else {
            /** @var mixed $config */
            $config = json_decode($contents);

            if (! $config instanceof stdClass) {
                return null;
            }
        }

        $config->{$configKey} ??= new stdClass;

        if (! $config->{$configKey} instanceof stdClass) {
            return null;
        }

        $config->{$configKey}->{$serverKey} = $server;

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded."\n";
    }
}
