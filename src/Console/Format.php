<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Input\InputInterface;

enum Format: string
{
    case Table = 'table';
    case Json = 'json';
    case Ndjson = 'ndjson';
    case Csv = 'csv';
    case Yaml = 'yaml';

    /**
     * The format of one invocation: --format wins, then --json, then UNOLIA_FORMAT, then table.
     *
     * @param  array<string, string>  $env
     */
    public static function fromOptions(InputInterface $input, array $env): self
    {
        $format = $input->getParameterOption('--format', null, true);

        if (is_string($format) && $format !== '') {
            return self::parse($format);
        }

        if ($input->hasParameterOption('--json', true)) {
            return self::Json;
        }

        $fromEnv = $env['UNOLIA_FORMAT'] ?? null;

        if (is_string($fromEnv) && $fromEnv !== '') {
            return self::parse($fromEnv);
        }

        return self::Table;
    }

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw CliError::usage(
                sprintf('unknown format %s', $value),
                'Use one of table, json, ndjson, csv, yaml',
                ['candidates' => self::names()],
            );
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** True when the format is meant for a machine rather than a person. */
    public function isStructured(): bool
    {
        return $this !== self::Table;
    }
}
