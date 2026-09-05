<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

use Symfony\Component\Yaml\Yaml;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Arr;

/**
 * herd.yml is Herd's file, not ours. We only ever touch the keys Herd documents, and
 * we keep everything else exactly as we found it.
 */
final class HerdYaml
{
    /**
     * The Herd service name for each database engine we can see on a managed server.
     */
    private const DATABASE_SERVICES = [
        'mysql' => 'mysql',
        'mariadb' => 'mysql',
        'postgres' => 'postgresql',
        'postgresql' => 'postgresql',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        /** @var mixed $parsed */
        $parsed = Yaml::parse($contents);

        if (! is_array($parsed)) {
            throw CliError::usage(sprintf('%s is not valid YAML', $path));
        }

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(HerdPlanInput $input): array
    {
        $document = ['name' => $input->name];

        if ($input->php !== null) {
            $document['php'] = $input->php;
        }

        if ($input->secured !== null) {
            $document['secured'] = $input->secured;
        }

        if ($input->aliases !== []) {
            $document['aliases'] = array_values(array_unique($input->aliases));
        }

        $service = self::databaseService($input->databaseEngine);

        if ($input->withServices && $service !== null && $input->databaseVersion !== null) {
            $document['services'] = [
                $service => [
                    'version' => $input->databaseVersion,
                    'port' => '${DB_PORT}',
                ],
            ];
        }

        if ($input->hasForge()) {
            $document['integrations'] = [
                'forge' => [
                    (string) $input->forgeDomain => [
                        'server-id' => $input->forgeServerId,
                        'site-id' => $input->forgeSiteId,
                    ],
                ],
            ];
        }

        return $document;
    }

    public static function databaseService(?string $engine): ?string
    {
        if ($engine === null) {
            return null;
        }

        return self::DATABASE_SERVICES[strtolower($engine)] ?? null;
    }

    /**
     * Desired wins on managed keys, everything else is preserved verbatim.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $desired
     * @return array<string, mixed>
     */
    public static function merge(array $existing, array $desired): array
    {
        $merged = $existing;

        foreach (['name', 'php', 'secured', 'aliases'] as $key) {
            if (array_key_exists($key, $desired)) {
                $merged[$key] = $desired[$key];
            }
        }

        if (isset($desired['services']) && is_array($desired['services'])) {
            $services = isset($merged['services']) && is_array($merged['services']) ? $merged['services'] : [];

            foreach ($desired['services'] as $name => $service) {
                $services[$name] = $service;
            }

            $merged['services'] = $services;
        }

        $desiredForge = Arr::get($desired, 'integrations.forge');

        if (is_array($desiredForge)) {
            $integrations = isset($merged['integrations']) && is_array($merged['integrations']) ? $merged['integrations'] : [];
            $forge = isset($integrations['forge']) && is_array($integrations['forge']) ? $integrations['forge'] : [];

            foreach ($desiredForge as $domain => $ids) {
                $forge[$domain] = $ids;
            }

            $integrations['forge'] = $forge;
            $merged['integrations'] = $integrations;
        }

        return $merged;
    }

    /**
     * A readable diff of the managed keys only.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $merged
     * @return list<array{0: string, 1: string}>
     */
    public static function diff(array $existing, array $merged): array
    {
        $lines = [];

        foreach ([...self::managedKeys(), ...self::dynamicPaths($merged, $existing)] as $path) {
            $before = Arr::has($existing, $path) ? self::inline(Arr::get($existing, $path)) : null;
            $after = Arr::has($merged, $path) ? self::inline(Arr::get($merged, $path)) : null;

            if ($before === $after) {
                continue;
            }

            if ($before !== null) {
                $lines[] = ['-', $path.': '.$before];
            }

            if ($after !== null) {
                $lines[] = ['+', $path.': '.$after];
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function managedKeys(): array
    {
        return ['name', 'php', 'secured', 'aliases'];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function dump(array $document): string
    {
        if (isset($document['php'])) {
            $document['php'] = (string) (is_scalar($document['php']) ? $document['php'] : '');
        }

        return Yaml::dump($document, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    private static function inline(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return trim(Yaml::dump($value, 1, 2));
        }

        return (string) (is_scalar($value) ? $value : '');
    }

    /**
     * The services and forge entries actually present, so the diff covers them too.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $existing
     * @return list<string>
     */
    private static function dynamicPaths(array $merged, array $existing): array
    {
        $paths = [];

        foreach ([$merged, $existing] as $document) {
            $services = $document['services'] ?? null;

            if (is_array($services)) {
                foreach (array_keys($services) as $name) {
                    $paths['services.'.$name.'.version'] = true;
                    $paths['services.'.$name.'.port'] = true;
                }
            }

            $forge = Arr::get($document, 'integrations.forge');

            if (is_array($forge)) {
                foreach (array_keys($forge) as $domain) {
                    $paths['integrations.forge.'.$domain.'.server-id'] = true;
                    $paths['integrations.forge.'.$domain.'.site-id'] = true;
                }
            }
        }

        return array_keys($paths);
    }
}
