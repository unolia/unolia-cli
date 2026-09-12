<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Api\Requests\Domains\ListDomains;
use Unolia\Cli\Api\Requests\Domains\ShowRecord;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Str;

/**
 * Which zone a dns command works on, and which record. The zone comes from
 * --zone, from a full hostname that ends with one of the project's zones, or
 * from the checkout when the project has exactly one. Names are relative to
 * the zone on the way out (`www`, `@`) and accepted both ways on the way in.
 */
trait ResolvesZones
{
    /**
     * The zone a command works on. $name is a record name that may carry the
     * zone in its tail; $explicit is a zone typed as an argument.
     */
    protected function zone(?string $explicit = null, ?string $name = null): string
    {
        $explicit = $this->optionString('zone') ?? $explicit;

        if ($explicit !== null && $explicit !== '') {
            return self::tidy($explicit);
        }

        $zones = $this->projectZones();

        if ($name !== null && $name !== '@') {
            $full = self::tidy($name);

            foreach ($zones as $zone) {
                if ($full === $zone || str_ends_with($full, '.'.$zone)) {
                    return $zone;
                }
            }

            // A full hostname whose zone is not in this project: still a
            // zone name, if the API knows it. The API says no otherwise.
            if ($zones === [] && substr_count($full, '.') >= 1) {
                return $full;
            }
        }

        if (count($zones) === 1) {
            return $zones[0];
        }

        if ($zones === []) {
            throw CliError::usage(
                'no zone to work on',
                'Pass --zone <domain>, or run this in a checkout whose project has a zone. unolia domains lists them.',
            );
        }

        if ($this->ask()->interactive()) {
            return $this->ask()->select('Which zone?', array_combine($zones, $zones), '--zone');
        }

        throw CliError::usage(
            'this project has several zones',
            'Pass --zone <domain>, or use the full hostname. Zones: '.implode(', ', $zones),
            ['flag' => '--zone', 'candidates' => $zones],
        );
    }

    /**
     * The zones of the resolved project, or of the team outside a checkout.
     *
     * @return list<string>
     */
    protected function projectZones(): array
    {
        $project = $this->context(Need::None)->project;
        $zones = [];

        foreach ($this->collection(new ListDomains(array_filter(['project' => $project, 'per_page' => 100]))) as $zone) {
            if (is_string($zone['domain'] ?? null)) {
                $zones[] = self::tidy($zone['domain']);
            }
        }

        sort($zones);

        return $zones;
    }

    /** `www` for www.acme.com in acme.com, `@` for the apex. */
    public static function relativeName(mixed $full, string $zone): string
    {
        $full = self::tidy(Str::scalar($full, ''));

        if ($full === $zone || $full === '') {
            return '@';
        }

        return str_ends_with($full, '.'.$zone) ? substr($full, 0, -strlen($zone) - 1) : $full;
    }

    /** acme.com for `@`, www.acme.com for `www` and for www.acme.com alike. */
    public static function fullName(string $name, string $zone): string
    {
        $name = self::tidy($name);

        if ($name === '@' || $name === '') {
            return $zone;
        }

        return $name === $zone || str_ends_with($name, '.'.$zone) ? $name : $name.'.'.$zone;
    }

    /** Lowercase, no trailing dot, no spaces. */
    public static function tidy(string $name): string
    {
        return rtrim(strtolower(trim($name)), '.');
    }

    /**
     * One record from an id, or from a name and an optional type that match
     * exactly one record of the zone. Anything else is an error with the rows.
     *
     * @return array<string, mixed>
     */
    protected function record(string $reference, ?string $type = null): array
    {
        if (ctype_digit($reference)) {
            return $this->fetch(new ShowRecord($reference));
        }

        $zone = $this->zone(null, $reference);
        $rows = $this->collection(new ListDomainRecords($zone, array_filter([
            'name' => self::fullName($reference, $zone),
            'type' => $type === null ? null : strtoupper($type),
            'per_page' => 50,
        ])));

        // Older servers ignore the filters; keep the rows that match anyway.
        $rows = array_values(array_filter($rows, function (array $row) use ($reference, $zone, $type): bool {
            $sameName = self::tidy(Str::scalar($row['name'] ?? null, '')) === self::fullName($reference, $zone);

            return $sameName && ($type === null || strcasecmp(Str::scalar($row['type'] ?? null, ''), $type) === 0);
        }));

        if (count($rows) === 1) {
            return $rows[0];
        }

        $label = trim(self::relativeName(self::fullName($reference, $zone), $zone).' '.strtoupper((string) $type));

        if ($rows === []) {
            throw CliError::notFound(
                sprintf('no %s record in %s', $label, $zone),
                'Run unolia dns to see the records; an id from the list works too.',
            );
        }

        throw CliError::usage(
            sprintf('%s matches %d records in %s', $label, count($rows), $zone),
            'Name one by id: '.implode(', ', array_map(static fn (array $row): string => '#'.Str::scalar($row['id'] ?? null).' '.Str::scalar($row['type'] ?? null).' '.Str::limit(Str::scalar($row['value'] ?? null, ''), 40), $rows)),
            ['candidates' => array_map(static fn (array $row): string => Str::scalar($row['id'] ?? null), $rows)],
        );
    }

    /**
     * The value with its priority in front, once: providers store MX and SRV
     * values with the priority already in them, some do not.
     *
     * @param  array<string, mixed>  $record
     */
    public static function displayValue(array $record): string
    {
        $value = Str::scalar($record['value'] ?? null, '');
        $priority = $record['priority'] ?? null;

        if (! is_numeric($priority) || preg_match('/^\d+\s/', $value) === 1) {
            return $value;
        }

        return $priority.' '.$value;
    }

    /**
     * "www A 95.179.220.235" the way a person says a record.
     *
     * @param  array<string, mixed>  $record
     */
    public static function describe(array $record, string $zone): string
    {
        return trim(sprintf(
            '%s %s %s',
            self::relativeName($record['name'] ?? null, $zone),
            Str::scalar($record['type'] ?? null, ''),
            self::displayValue($record),
        ));
    }
}
