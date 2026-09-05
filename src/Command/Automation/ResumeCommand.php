<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ResumeAutomationRun;
use Unolia\Cli\Api\Requests\Automations\ShowAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\AutomationRunTarget;

/**
 * Answer the question a parked run is waiting on.
 */
final class ResumeCommand extends BaseCommand
{
    use ResolvesRuns;
    use Watches;

    protected function canonical(): string
    {
        return 'automation:resume';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Answer a run that is waiting for input');
    }

    protected function define(): void
    {
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or a prefix of it');
        $this->addOption('input', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A key=value answer');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Follow the run after answering');
        $this->addWatchOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Answer and continue' => 'unolia automation resume 01J9A2 --input reboot=true --wait',
            'Answer from a terminal' => 'unolia automation resume 01J9A2',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));
        $run = $this->fetch(new ShowAutomationRun($ulid));
        $blocks = $this->inputBlocks($run);

        $answers = $this->answers($blocks);

        if ($this->dryRun()) {
            $this->out()->record(['run' => $ulid, 'inputs' => $answers]);

            return ExitCode::Ok;
        }

        $resumed = $this->fetch(new ResumeAutomationRun($ulid, ['inputs' => $answers]));

        if (! $this->optionBool('wait')) {
            if ($this->structured()) {
                $this->out()->record($resumed);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Run %s resumed', $ulid));

            return ExitCode::Ok;
        }

        $result = $this->follow(new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds()));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }

    /**
     * @param  array<string, mixed>  $run
     * @return list<array<string, mixed>>
     */
    private function inputBlocks(array $run): array
    {
        foreach (is_array($run['steps'] ?? null) ? $run['steps'] : [] as $step) {
            if (! is_array($step) || ($step['state'] ?? null) !== 'awaiting_input') {
                continue;
            }

            $blocks = [];

            foreach (is_array($step['input_blocks'] ?? null) ? $step['input_blocks'] : [] as $block) {
                if (is_array($block)) {
                    $blocks[] = $block;
                }
            }

            return $blocks;
        }

        throw CliError::usage(
            'this run is not waiting for anything',
            'Check it with unolia automation view.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function answers(array $blocks): array
    {
        $given = [];

        foreach ($this->optionList('input') as $pair) {
            if (! str_contains($pair, '=')) {
                throw CliError::usage(sprintf('--input %s is not a key=value pair', $pair));
            }

            [$key, $value] = explode('=', $pair, 2);
            $given[trim($key)] = $value;
        }

        if ($given !== []) {
            return $this->cast($blocks, $given);
        }

        if (! $this->ask()->interactive()) {
            $expected = [];

            foreach ($blocks as $block) {
                if (is_string($block['field'] ?? null)) {
                    $expected[$block['field']] = (string) ($block['label'] ?? '');
                }
            }

            throw CliError::missingInput('--input', $expected);
        }

        $answers = [];

        foreach ($blocks as $block) {
            $field = $block['field'] ?? null;

            if (! is_string($field)) {
                continue;
            }

            $label = (string) ($block['label'] ?? $field);
            $answers[$field] = match ((string) ($block['kind'] ?? 'text')) {
                'confirm' => $this->ask()->confirm($label),
                'select' => $this->ask()->select($label, $this->options($block), '--input'),
                'password' => $this->ask()->password($label, '--input'),
                default => $this->ask()->text($label, '--input'),
            };
        }

        return $answers;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private function options(array $block): array
    {
        $options = [];

        foreach (is_array($block['options'] ?? null) ? $block['options'] : [] as $key => $label) {
            if (is_scalar($label)) {
                $options[is_int($key) ? (string) $label : (string) $key] = (string) $label;
            }
        }

        return $options;
    }

    /**
     * Turn the strings a script passed into the types the blocks declare.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, string>  $given
     * @return array<string, mixed>
     */
    private function cast(array $blocks, array $given): array
    {
        $kinds = [];

        foreach ($blocks as $block) {
            if (is_string($block['field'] ?? null)) {
                $kinds[$block['field']] = (string) ($block['kind'] ?? 'text');
            }
        }

        $answers = [];

        foreach ($given as $field => $value) {
            $answers[$field] = ($kinds[$field] ?? 'text') === 'confirm'
                ? in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true)
                : $value;
        }

        return $answers;
    }
}
