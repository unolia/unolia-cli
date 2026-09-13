<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Laravel\Prompts\Prompt;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Support\Str;

/**
 * A question the API asks travels as input blocks: on the step a run stopped
 * at, on the fix an issue carries. This turns those blocks into prompts on a
 * terminal and into typed answers from --input flags in a pipe, the same way
 * for every command that meets one.
 *
 * A question's answer is data, never consent: --yes says nothing about which
 * servers to reboot or where a logo lives, so no block is ever answered by it.
 */
trait AsksInputBlocks
{
    /**
     * The step a run stopped at, or null when nothing is waiting.
     *
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>|null
     */
    protected static function awaitingStep(array $run): ?array
    {
        foreach (is_array($run['steps'] ?? null) ? $run['steps'] : [] as $step) {
            if (is_array($step) && ($step['state'] ?? null) === 'awaiting_input') {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<array<string, mixed>>
     */
    protected static function blocksOf(array $step): array
    {
        $blocks = [];

        foreach (is_array($step['input_blocks'] ?? null) ? $step['input_blocks'] : [] as $block) {
            if (is_array($block) && is_string($block['field'] ?? null) && $block['field'] !== '') {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * One prompt per block. Ctrl-C leaves things as they were and says how to
     * come back, $cancelHint, rather than the bare exit a prompt does on its own.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    protected function askBlocks(array $blocks, string $cancelHint): array
    {
        if (! $this->ask()->interactive()) {
            throw CliError::missingInput('--input', self::expectedInputs($blocks));
        }

        Prompt::cancelUsing(static function () use ($cancelHint): never {
            throw CliError::interrupted($cancelHint);
        });

        try {
            $answers = [];

            foreach ($blocks as $block) {
                /** @var string $field */
                $field = $block['field'];
                $answers[$field] = $this->askBlock($block);
            }

            return $answers;
        } finally {
            Prompt::cancelUsing(null);
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function askBlock(array $block): mixed
    {
        /** @var string $field */
        $field = $block['field'];
        $label = Str::scalar($block['label'] ?? null, $field);
        $hint = Str::scalar($block['help'] ?? null, '');
        $required = ($block['required'] ?? false) === true;
        $default = $block['default'] ?? null;
        $options = self::optionsOf($block);

        return match (Str::scalar($block['kind'] ?? null, 'text')) {
            'confirm' => $this->ask()->yesNo($label, '--input', self::truthy($default, true), $hint),
            'select' => $this->ask()->select($label, $options, '--input', is_scalar($default) && isset($options[(string) $default]) ? (string) $default : null, $hint),
            'multiselect' => $this->ask()->multiselect($label, $options, '--input', self::listOf($default), $hint, $required),
            'password' => $this->ask()->password($label, '--input', $hint),
            'textarea' => $this->ask()->textarea($label, '--input', Str::scalar($default, ''), $hint, $required),
            default => $this->ask()->text($label, '--input', '', Str::scalar($default, ''), $hint, $required),
        };
    }

    /**
     * Answers from --input key=value flags, typed the way each block expects:
     * a confirm becomes a boolean, a multiselect a list from a comma separated
     * value, and a choice may be given by its label as well as by its value.
     * Null when no flag was given, so the caller can ask instead.
     *
     * @param  list<string>  $pairs
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    protected static function answersFromFlags(array $pairs, array $blocks): ?array
    {
        if ($pairs === []) {
            return null;
        }

        $byField = [];

        foreach ($blocks as $block) {
            /** @var string $field */
            $field = $block['field'];
            $byField[$field] = $block;
        }

        $answers = [];

        foreach ($pairs as $pair) {
            if (! str_contains($pair, '=')) {
                throw CliError::usage(sprintf('--input %s is not a key=value pair', $pair));
            }

            [$field, $value] = explode('=', $pair, 2);
            $field = trim($field);
            $block = $byField[$field] ?? [];

            $answers[$field] = match (Str::scalar($block['kind'] ?? null, 'text')) {
                'confirm' => self::truthy($value, false),
                'select' => self::choice($block, trim($value)),
                'multiselect' => array_map(
                    static fn (string $one): string => self::choice($block, $one),
                    array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $one): bool => $one !== '')),
                ),
                default => $value,
            };
        }

        return $answers;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, string>
     */
    protected static function expectedInputs(array $blocks): array
    {
        $expected = [];

        foreach ($blocks as $block) {
            /** @var string $field */
            $field = $block['field'];
            $expected[$field] = Str::scalar($block['label'] ?? null, $field);
        }

        return $expected;
    }

    /**
     * The choices as value => label. The API sends an ordered list of
     * {value, label} pairs; a map or a bare list of labels reads the same.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private static function optionsOf(array $block): array
    {
        $options = [];

        foreach (is_array($block['options'] ?? null) ? $block['options'] : [] as $key => $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? null;
                $label = $option['label'] ?? $value;
            } else {
                $value = is_int($key) ? $option : $key;
                $label = $option;
            }

            if (is_scalar($value) && is_scalar($label)) {
                $options[(string) $value] = (string) $label;
            }
        }

        return $options;
    }

    /**
     * A choice typed in a flag, by value or by label.
     *
     * @param  array<string, mixed>  $block
     */
    private static function choice(array $block, string $given): string
    {
        $options = self::optionsOf($block);

        if ($options === [] || isset($options[$given])) {
            return $given;
        }

        foreach ($options as $value => $label) {
            if (strcasecmp($label, $given) === 0) {
                // PHP keeps a numeric key as an int, whatever it was set as.
                return (string) $value;
            }
        }

        return $given;
    }

    private static function truthy(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private static function listOf(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }

        $list = [];

        foreach (is_array($value) ? $value : [] as $one) {
            if (is_scalar($one)) {
                $list[] = (string) $one;
            }
        }

        return $list;
    }
}
