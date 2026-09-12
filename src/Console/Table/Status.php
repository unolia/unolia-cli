<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

/**
 * One vocabulary of state across every list: a glyph that reads at a glance,
 * and the word only when the state is worth a word. Green is well, amber is
 * in between or in progress, red is broken, dim is off.
 */
final class Status
{
    private const GREEN = 'green';

    private const AMBER = 'yellow';

    private const RED = 'red';

    public static function glyph(mixed $status): Cell
    {
        // A glyph is for eyes; a pipe gets nothing here and the word from word().
        return (match (self::tone($status)) {
            'ok' => Cell::text('●')->color(self::GREEN),
            'busy' => Cell::text('◐')->color(self::AMBER),
            'bad' => Cell::text('✕')->color(self::RED),
            'off' => Cell::text('○')->dim(),
            default => Cell::text('·')->dim(),
        })->plain('');
    }

    /** The state as a word, in the glyph's colour, or nothing when it is the quiet normal. */
    public static function word(mixed $status, string $quiet = 'active'): Cell
    {
        if (! is_string($status) || $status === '') {
            return Cell::empty();
        }

        $word = str_replace(['_', '-'], ' ', $status);

        // The quiet normal says nothing on a terminal, where the glyph already
        // did; a pipe still gets the word so a script can grep for it.
        if ($status === $quiet) {
            return Cell::empty()->plain($word);
        }

        $cell = Cell::text($word)->plain($word);

        return match (self::tone($status)) {
            'ok' => $cell->color(self::GREEN),
            'busy' => $cell->color(self::AMBER),
            'bad' => $cell->color(self::RED),
            default => $cell->dim(),
        };
    }

    /**
     * A severity as a glyph: red for what is broken or urgent, amber for what
     * should be looked at, dim for the merely informative.
     */
    public static function severity(mixed $severity): Cell
    {
        return (match (self::severityTone($severity)) {
            'bad' => Cell::text('✕')->color(self::RED),
            'busy' => Cell::text('◐')->color(self::AMBER),
            'low' => Cell::text('·')->dim(),
            default => Cell::text('·')->dim(),
        })->plain('');
    }

    /** The severity as a word in the glyph's colour, always shown: it is what the row is about. */
    public static function severityWord(mixed $severity): Cell
    {
        if (! is_string($severity) || $severity === '') {
            return Cell::empty();
        }

        $cell = Cell::text($severity)->plain($severity);

        return match (self::severityTone($severity)) {
            'bad' => $cell->color(self::RED),
            'busy' => $cell->color(self::AMBER),
            default => $cell->dim(),
        };
    }

    private static function severityTone(mixed $severity): string
    {
        return match (is_string($severity) ? strtolower($severity) : '') {
            'critical', 'major', 'error', 'high', 'urgent' => 'bad',
            'warning', 'medium', 'minor' => 'busy',
            'info', 'low', 'notice' => 'low',
            default => 'unknown',
        };
    }

    private static function tone(mixed $status): string
    {
        if (! is_string($status)) {
            return 'unknown';
        }

        return match (strtolower($status)) {
            'active', 'success', 'succeeded', 'completed', 'ready', 'healthy', 'ok', 'up', 'passed', 'resolved', 'connected', 'verified', 'fixed' => 'ok',
            'running', 'pending', 'queued', 'deploying', 'in_progress', 'waiting', 'maintenance', 'degraded', 'warning', 'awaiting_input', 'syncing' => 'busy',
            'failed', 'failure', 'error', 'errored', 'offline', 'down', 'broken', 'expired', 'timed_out', 'open' => 'bad',
            'inactive', 'disabled', 'paused', 'archived', 'cancelled', 'canceled', 'skipped', 'revoked', 'ignored' => 'off',
            default => 'unknown',
        };
    }
}
