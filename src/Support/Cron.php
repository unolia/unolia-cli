<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use Lorisleiva\CronTranslator\CronTranslator;

final class Cron
{
    /** A cron expression in words, "every day at 9:00am"; one that cannot be read stays as written. */
    public static function describe(string $expression): string
    {
        try {
            return lcfirst(CronTranslator::translate($expression));
        } catch (\Throwable) {
            return $expression;
        }
    }
}
