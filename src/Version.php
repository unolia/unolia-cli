<?php

declare(strict_types=1);

namespace Unolia\Cli;

use Composer\InstalledVersions;

final class Version
{
    /**
     * Box rewrites this literal with `git describe --tags` when it compiles the phar.
     * It is a property rather than a constant so nothing folds the check away.
     */
    private static string $boxVersion = '@git_version@';

    public static function current(): string
    {
        if (! str_contains(self::$boxVersion, 'git_version')) {
            return ltrim(self::$boxVersion, 'v');
        }

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('unolia/unolia-cli')) {
            $version = InstalledVersions::getPrettyVersion('unolia/unolia-cli');

            if (is_string($version) && $version !== '' && ! str_starts_with($version, 'dev-')) {
                return ltrim($version, 'v');
            }
        }

        return 'dev';
    }

    /** The User-Agent every request carries. */
    public static function userAgent(): string
    {
        return sprintf(
            'UnoliaCLI/%s (%s; %s; PHP %d.%d.%d)',
            self::current(),
            function_exists('php_uname') ? php_uname('s') : 'unknown',
            function_exists('php_uname') ? php_uname('m') : 'unknown',
            PHP_MAJOR_VERSION,
            PHP_MINOR_VERSION,
            PHP_RELEASE_VERSION,
        );
    }
}
