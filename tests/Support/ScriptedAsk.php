<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\Face;

/**
 * The TTY face without a terminal: answers are queued by label, and an unexpected
 * question fails the test instead of hanging. In the pipe face the real guard runs,
 * so tests see the same exit 2 a script would.
 */
final class ScriptedAsk extends Ask
{
    /** @var list<array{label: string, answer: mixed}> */
    private array $asked = [];

    /**
     * @param  array<string, mixed>  $answers  label or a fragment of it => answer
     */
    public function __construct(Face $face, private array $answers = [])
    {
        parent::__construct($face);
    }

    public function text(string $label, string $flag, string $placeholder = '', string $default = '', string $hint = ''): string
    {
        if (! $this->face->interactive) {
            return parent::text($label, $flag, $placeholder, $default, $hint);
        }

        return (string) $this->answer($label, $flag, $default === '' ? null : $default);
    }

    public function password(string $label, string $flag, string $hint = ''): string
    {
        if (! $this->face->interactive) {
            return parent::password($label, $flag, $hint);
        }

        return (string) $this->answer($label, $flag);
    }

    public function select(string $label, array $options, string $flag, int|string|null $default = null, string $hint = ''): string
    {
        if (! $this->face->interactive) {
            return parent::select($label, $options, $flag, $default, $hint);
        }

        return (string) $this->answer($label, $flag, $default);
    }

    public function search(string $label, callable $options, string $flag, array $candidates = [], string $placeholder = ''): string
    {
        if (! $this->face->interactive) {
            return parent::search($label, $options, $flag, $candidates, $placeholder);
        }

        return (string) $this->answer($label, $flag);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if ($this->face->yes) {
            return true;
        }

        if (! $this->face->interactive) {
            return parent::confirm($question, $default);
        }

        return (bool) $this->answer($question, '--yes', $default);
    }

    /**
     * @return list<string>
     */
    public function questions(): array
    {
        return array_map(static fn (array $entry): string => $entry['label'], $this->asked);
    }

    private function answer(string $label, string $flag, mixed $default = null): mixed
    {
        foreach ($this->answers as $needle => $answer) {
            if (stripos($label, (string) $needle) !== false) {
                unset($this->answers[$needle]);
                $this->asked[] = ['label' => $label, 'answer' => $answer];

                return $answer;
            }
        }

        if ($default !== null) {
            $this->asked[] = ['label' => $label, 'answer' => $default];

            return $default;
        }

        Assert::fail(sprintf('The command asked "%s" (%s) and the test had no answer for it.', $label, $flag));
    }
}
