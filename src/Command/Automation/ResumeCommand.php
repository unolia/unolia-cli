<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Automations\ResumeAutomationRun;
use Unolia\Cli\Api\Requests\Automations\ShowAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\FollowsAutomationRuns;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\AutomationRunTarget;
use Unolia\Cli\Watch\TargetState;

/**
 * Answer the question a parked run is waiting on. On a terminal the run's
 * steps replay as tasks down to the one that is asking, the question is
 * asked there, and the list carries on once answered. A pipe answers from
 * --input flags and hands the run back, or waits with --wait.
 */
final class ResumeCommand extends BaseCommand
{
    use FollowsAutomationRuns;
    use ResolvesRuns;

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
        $this->addArgument('run', InputArgument::REQUIRED, 'Run ULID or its short id, the last six characters');
        $this->addOption('input', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A field=value answer. A choice by value or label, several separated by commas');
        $this->addFollowOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Answer from a terminal' => 'unolia automation resume PC0XCA',
            'Answer yes or no' => 'unolia automation resume PC0XCA --input reboot=yes',
            'Pick several' => 'unolia automation resume PC0XCA --input selected_server_ids=web-01,db-01 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $ulid = $this->runUlid((string) $this->argumentString('run'));
        $run = $this->fetch(new ShowAutomationRun($ulid));
        $step = self::awaitingStep($run);

        if ($step === null) {
            throw CliError::usage(
                'this run is not waiting for anything',
                sprintf('unolia automation watch %s follows it.', Str::shortId($ulid)),
            );
        }

        $blocks = self::blocksOf($step);
        $answers = self::answersFromFlags($this->optionList('input'), $blocks);
        $progress = $this->out()->face()->interactive && ! $this->structured() && ! $this->optionBool('no-progress');

        if ($this->dryRun()) {
            $this->out()->record(['run' => $ulid, 'inputs' => $answers ?? $this->askBlocks($blocks, $ulid)]);

            return ExitCode::Ok;
        }

        // A terminal answers where the step stopped, flags or not, and
        // watches the rest of the run from there.
        if ($progress) {
            return $this->followRunSteps($ulid, new TargetState($run), $answers);
        }

        $answers ??= $this->askBlocks($blocks, $ulid);
        $resumed = $this->fetch(new ResumeAutomationRun($ulid, ['inputs' => $answers]));
        $short = Str::shortId($ulid);

        return $this->followByDefault(
            new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds()),
            sprintf('Resuming %s', Str::scalar(Arr::get($resumed, 'automation.name'), 'the run')),
            $resumed,
            sprintf('Run %s resumed · unolia automation watch %s', $short, $short),
            sprintf('unolia automation logs %s shows the whole run.', $short),
        );
    }
}
