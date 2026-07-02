<?php

namespace Unolia\UnoliaCLI\Mcp;

enum Platform
{
    case Darwin;
    case Linux;
    case Windows;

    public static function current(): self
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => self::Darwin,
            'Windows' => self::Windows,
            default => self::Linux,
        };
    }
}
