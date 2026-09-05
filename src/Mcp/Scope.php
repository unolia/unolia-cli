<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

/**
 * Where the connector goes: every project of the user, or the current directory only.
 */
enum Scope: string
{
    case Global = 'global';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'All projects (recommended)',
            self::Local => 'This directory only',
        };
    }
}
