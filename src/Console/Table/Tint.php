<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

/**
 * Each provider in its own brand colour, the same in every list, so a column
 * of providers reads by colour before it reads by name. Brands that are black
 * and white (Laravel Cloud, GitHub) take the terminal's own foreground, which
 * is white on a dark theme and black on a light one.
 */
final class Tint
{
    /** The terminal's default foreground, for monochrome brands. */
    public const DEFAULT = 'default';

    public static function provider(mixed $slug): ?string
    {
        return match (is_string($slug) ? strtolower($slug) : '') {
            'forge' => '#19b69b',
            'ploi' => '#1853db',
            'laravel-cloud', 'cloud' => self::DEFAULT,
            'ovh' => '#0050d7',
            'github', 'pages' => self::DEFAULT,
            'gitlab' => '#fc6d26',
            'cloudflare' => '#f38020',
            'digitalocean' => '#0080ff',
            'vultr' => '#007bfc',
            'hetzner' => '#d50c2d',
            'aws' => '#ff9900',
            default => null,
        };
    }
}
