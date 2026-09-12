<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

/**
 * A faint hue per provider, the same in every list, so a column of providers
 * reads by colour before it reads by name. Low saturation on purpose: it is a
 * hint, not a highlight.
 */
final class Tint
{
    public static function provider(mixed $slug): ?string
    {
        return match (is_string($slug) ? strtolower($slug) : '') {
            'forge' => '#7aa7ff',
            'laravel-cloud', 'cloud' => '#c4a0ff',
            'ploi' => '#6fd6c2',
            'ovh' => '#9aa3ad',
            'github', 'pages' => '#b0b8c4',
            'gitlab' => '#f0a35e',
            'cloudflare' => '#f6a35b',
            default => null,
        };
    }
}
