<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Concerns;

use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\StepLog;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\RecordTarget;
use Unolia\Cli\Watch\WatchResult;

/**
 * After a record is written: follow it until the provider and Unolia agree it
 * is live. A terminal follows inside a task by default, --no-progress hands
 * the record back at once, a pipe waits only with --wait.
 */
trait PropagatesRecords
{
    use Watches;

    protected function addPropagationOptions(): void
    {
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Block until the record is verified, also in a pipe');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Return as soon as the record is saved instead of following it');
        $this->addWatchOptions('2m', 2);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function propagate(array $record, string $zone, string $verb): ExitCode
    {
        $id = $record['id'] ?? null;

        if ($this->structured()) {
            if (! $this->optionBool('wait') || ! is_numeric($id)) {
                $this->out()->record($record);

                return ExitCode::Ok;
            }

            $result = $this->follow(new RecordTarget($this->api(), (int) $id, false));
            $this->out()->record($result->state->data);

            return $result->exitCode;
        }

        $described = self::describe($record, $zone);
        $progress = $this->out()->face()->interactive && ! $this->optionBool('no-progress');

        if (! is_numeric($id) || (! $progress && ! $this->optionBool('wait'))) {
            $this->out()->info(sprintf('%s %s', $verb, $described).(is_numeric($id) ? sprintf(' · unolia dns watch %d', (int) $id) : ''));

            return ExitCode::Ok;
        }

        $target = new RecordTarget($this->api(), (int) $id, false);

        if (! $progress) {
            return $this->follow($target)->exitCode;
        }

        $result = $this->ask()->task(
            sprintf('%s %s, waiting for it to propagate', $verb, $described),
            fn (StepLog $log): WatchResult => $this->follow($target, $log),
        );

        if ($result->exitCode !== ExitCode::Ok) {
            $this->out()->note(sprintf('Still propagating. unolia dns watch %d keeps looking; unolia dns check compares with a resolver.', (int) $id));
        }

        return $result->exitCode;
    }

    /** What the value of a type should look like, for the prompt. */
    protected static function hintFor(string $type): string
    {
        return match (strtoupper($type)) {
            'A' => 'An IPv4 address',
            'AAAA' => 'An IPv6 address',
            'CNAME', 'NS' => 'A hostname, such as target.example.com',
            'MX' => 'A mail server, such as mail.example.com; the priority goes in --priority',
            'TXT' => 'The text value',
            'CAA' => 'flags tag value, such as 0 issue "letsencrypt.org"',
            default => '',
        };
    }

    /** @var list<string> */
    protected const RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV', 'CAA', 'PTR'];

    protected static function checkType(string $type): string
    {
        $type = strtoupper(trim($type));

        if ($type === '' || preg_match('/^[A-Z0-9]{1,10}$/', $type) !== 1) {
            throw CliError::usage(sprintf('%s is not a record type', Str::scalar($type, '')), 'Types: '.implode(', ', self::RECORD_TYPES).', or any other the provider accepts.');
        }

        return $type;
    }
}
