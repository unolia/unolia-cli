<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Console\StepLog;

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
    /**
     * @param  array<string, mixed>  $answers  label or a fragment of it => answer
     * @param  (\Closure(): Out)|null  $out  where a task writes its lines, plainly
     */
    public function __construct(Face $face, private array $answers = [], private readonly ?\Closure $out = null)
    {
        parent::__construct($face);
    }

    /** A spinner without a terminal: the message as a plain line, so a test can see what was waited for. */
    public function spin(string $message, callable $callback): mixed
    {
        if ($this->face->interactive && $this->out !== null) {
            ($this->out)()->line($message);
        }

        return $callback();
    }

    /** A task without a terminal: the label, then every line and outcome as plain output. */
    public function task(string $label, callable $callback): mixed
    {
        if (! $this->face->interactive || $this->out === null) {
            return parent::task($label, $callback);
        }

        $out = ($this->out)();
        $out->line($label);

        return $callback(new class($out) implements StepLog
        {
            public function __construct(private readonly Out $out) {}

            public function line(string $message): void
            {
                $this->out->line('  '.$message);
            }

            public function success(string $message): void
            {
                $this->out->info($message);
            }

            public function warning(string $message): void
            {
                $this->out->warn($message);
            }

            public function error(string $message): void
            {
                $this->out->line('✗ '.$message);
            }

            public function subLabel(string $message): void
            {
                if ($message !== '') {
                    $this->out->line($message);
                }
            }
        });
    }

    public function text(string $label, string $flag, string $placeholder = '', string $default = '', string $hint = '', bool $required = true): string
    {
        if (! $this->face->interactive) {
            return parent::text($label, $flag, $placeholder, $default, $hint, $required);
        }

        return (string) $this->answer($label, $flag, $default === '' ? null : $default);
    }

    public function textarea(string $label, string $flag, string $default = '', string $hint = '', bool $required = true): string
    {
        if (! $this->face->interactive) {
            return parent::textarea($label, $flag, $default, $hint, $required);
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

    public function multiselect(string $label, array $options, string $flag, array $default = [], string $hint = '', bool $required = true): array
    {
        if (! $this->face->interactive) {
            return parent::multiselect($label, $options, $flag, $default, $hint, $required);
        }

        /** @var mixed $answer */
        $answer = $this->answer($label, $flag, $default === [] ? null : $default);

        return array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', is_array($answer) ? $answer : [$answer]));
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

    public function yesNo(string $question, string $flag, bool $default = true, string $hint = ''): bool
    {
        if (! $this->face->interactive) {
            return parent::yesNo($question, $flag, $default, $hint);
        }

        return (bool) $this->answer($question, $flag, $default);
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
