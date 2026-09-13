<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Laravel\Prompts\Support\Logger;
use Unolia\Cli\Api\Interrupt;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\task;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

/**
 * Every prompt goes through here. On a pipe the prompt becomes an exit 2 that names the
 * flag it wanted, so a script never blocks on a question nobody can answer.
 */
class Ask
{
    public function __construct(protected readonly Face $face) {}

    public function interactive(): bool
    {
        return $this->face->interactive;
    }

    public function text(string $label, string $flag, string $placeholder = '', string $default = '', string $hint = '', bool $required = true): string
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag);
        }

        return text(label: $label, placeholder: $placeholder, default: $default, required: $required, hint: $hint);
    }

    public function textarea(string $label, string $flag, string $default = '', string $hint = '', bool $required = true): string
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag);
        }

        return textarea(label: $label, default: $default, required: $required, hint: $hint);
    }

    public function password(string $label, string $flag, string $hint = ''): string
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag);
        }

        return password(label: $label, required: true, hint: $hint);
    }

    /**
     * @param  array<int|string, string>  $options
     */
    public function select(string $label, array $options, string $flag, int|string|null $default = null, string $hint = ''): string
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag, $options);
        }

        return (string) select(label: $label, options: $options, default: $default, hint: $hint);
    }

    /**
     * @param  array<int|string, string>  $options
     * @param  list<int|string>  $default
     * @return list<string>
     */
    public function multiselect(string $label, array $options, string $flag, array $default = [], string $hint = '', bool $required = true): array
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag, $options);
        }

        $selected = multiselect(label: $label, options: $options, default: $default, scroll: 10, required: $required, hint: $hint);

        return array_values(array_map(static fn (int|string $value): string => (string) $value, $selected));
    }

    /**
     * @param  callable(string): array<int|string, string>  $options
     * @param  array<int|string, string>  $candidates  shown in the pipe face error
     */
    public function search(string $label, callable $options, string $flag, array $candidates = [], string $placeholder = ''): string
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag, $candidates);
        }

        return (string) search(label: $label, options: $options, placeholder: $placeholder);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if ($this->face->yes) {
            return true;
        }

        if (! $this->face->interactive) {
            throw CliError::confirmationRequired($question);
        }

        return confirm(label: $question, default: $default);
    }

    /**
     * A yes-or-no question whose answer is data, not consent: whether to reboot
     * a server, say, asked by a run that stopped to ask. --yes says nothing
     * about it, so this never takes --yes for an answer, and a pipe is told
     * which flag carries one.
     */
    public function yesNo(string $question, string $flag, bool $default = true, string $hint = ''): bool
    {
        if (! $this->face->interactive) {
            throw CliError::missingInput($flag);
        }

        return confirm(label: $question, default: $default, hint: $hint);
    }

    /**
     * Something short to wait for, said in one line that goes away once it is
     * done: a spinner with the message on a terminal, nothing elsewhere. The
     * caller prints the outcome itself, so what stays on screen is its line.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function spin(string $message, callable $callback): mixed
    {
        if (! $this->face->interactive) {
            return $callback();
        }

        return spin(static function () use ($callback): mixed {
            // The spinner takes Ctrl+C for itself while it runs.
            Interrupt::rearm();

            return $callback();
        }, $message);
    }

    /**
     * Run a long step. On a terminal it is a Prompts task: a spinner with the
     * label, the tool's output scrolling underneath, and the success and error
     * lines kept once it is done. Elsewhere the callback runs with a quiet log
     * and the command's own report speaks afterwards.
     *
     * @template T
     *
     * @param  callable(StepLog): T  $callback
     * @return T
     */
    public function task(string $label, callable $callback): mixed
    {
        if (! $this->face->interactive) {
            return $callback(new QuietStepLog);
        }

        return task(
            label: $label,
            callback: static fn (Logger $logger): mixed => $callback(new PromptsStepLog($logger)),
            keepSummary: true,
        );
    }
}
