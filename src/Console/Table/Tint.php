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

    /**
     * DNS record types by family, so a zone reads by colour: addresses blue,
     * aliases cyan, mail and text magenta. Delegation and the rest take the
     * terminal's own foreground, dimmed by the caller when they matter less.
     */
    public static function recordType(mixed $type): ?string
    {
        return match (is_string($type) ? strtoupper($type) : '') {
            'A', 'AAAA' => '#7aa7ff',
            'CNAME', 'ALIAS', 'DNAME' => 'cyan',
            'MX', 'TXT', 'SPF', 'DKIM', 'DMARC', 'BIMI' => 'magenta',
            default => null,
        };
    }

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
            'aws', 'route53' => '#ff9900',
            'gandi' => '#4ebd9e',
            'porkbun' => '#ef7d73',
            'namecheap' => '#de3723',
            'ionos' => '#003d8f',
            'bunny' => '#ff7f2a',
            'bento' => '#8b5a2b',
            'domainchief' => '#2563eb',
            'mailgun' => '#f06b66',
            'postmark' => '#ffde00',
            'ohdear' => '#5b6cff',
            'uptimerobot' => '#3bd671',
            'slack' => '#4a154b',
            default => null,
        };
    }
}
