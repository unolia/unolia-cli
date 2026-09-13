<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Automation;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Terminal;
use Unolia\Cli\Api\Requests\Automations\CreateAutomationRun;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\FollowsAutomationRuns;
use Unolia\Cli\Command\Concerns\ResolvesRuns;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\AutomationRunTarget;

/**
 * Start an automation. --dry-run prints the plan the API computed, and changes nothing.
 */
final class RunCommand extends BaseCommand
{
    use FollowsAutomationRuns;
    use ResolvesRuns;

    protected function canonical(): string
    {
        return 'automation:run';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Start an automation');
    }

    protected function define(): void
    {
        $this->addArgument('automation', InputArgument::REQUIRED, 'Automation id or exact name');
        $this->addFollowOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'See the plan' => 'unolia automation run 7 --dry-run',
            'Run and follow it' => 'unolia automation run 7',
            'Start it and come back later' => 'unolia automation run 7 --no-progress',
            'Block in a script' => 'unolia automation run 7 --yes --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->automationId((string) $this->argumentString('automation'));

        // The plan first, in every case: it names what is about to start, and
        // says up front when the runner would refuse.
        $plan = $this->ask()->spin('Reading the plan', fn (): array => $this->fetch(new CreateAutomationRun($id, ['dry_run' => true])));

        if ($this->dryRun()) {
            return $this->preview($plan);
        }

        if (! $this->structured()) {
            $this->describe($plan);
        }

        $rejection = $plan['rejection'] ?? null;

        if (is_string($rejection) && $rejection !== '') {
            $this->out()->warn($rejection);

            return ExitCode::RemoteFailure;
        }

        $name = Str::scalar(Arr::get($plan, 'automation.name'), 'this automation');

        // An automation touches servers, so this asks, and a pipe needs --yes.
        if (! $this->ask()->confirm(sprintf('Run %s now?', $name))) {
            $this->out()->note('Nothing was started.');

            return ExitCode::Ok;
        }

        $run = $this->fetch(new CreateAutomationRun($id, ['dry_run' => false]));
        $ulid = is_string($run['ulid'] ?? null) ? $run['ulid'] : null;

        if ($ulid === null) {
            $this->out()->record($run);

            return ExitCode::RemoteFailure;
        }

        $short = Str::shortId($ulid);

        // A terminal shows the run as a checklist; --no-progress hands the
        // run back at once; a pipe waits only with --wait.
        if ($this->out()->face()->interactive && ! $this->structured() && ! $this->optionBool('no-progress')) {
            return $this->followRunSteps($ulid);
        }

        return $this->followByDefault(
            new AutomationRunTarget($this->api(), $ulid, $this->waitSeconds()),
            sprintf('Running %s', $name),
            $run,
            sprintf('Run %s started · unolia automation watch %s', $short, $short),
            sprintf('unolia automation logs %s shows the whole run.', $short),
        );
    }

    /**
     * What is about to start, above the question: the automation, its
     * recipe and schedule, the servers it will touch and the steps it will
     * take. Enough to catch the wrong id before saying yes.
     *
     * @param  array<string, mixed>  $plan
     */
    private function describe(array $plan): void
    {
        $automation = is_array($plan['automation'] ?? null) ? $plan['automation'] : [];
        $width = max(40, (new Terminal)->getWidth() - 14);

        $this->out()->line('');
        $this->out()->formatted(sprintf('  <options=bold>%s</> <fg=gray>#%s</>', OutputFormatter::escape(Str::scalar($automation['name'] ?? null, 'Automation')), Str::scalar($automation['id'] ?? null, '?')));

        $facts = [
            'Recipe' => Str::scalar(Arr::get($automation, 'recipe.name'), Str::scalar(Arr::get($automation, 'recipe.slug'), '')),
            'Schedule' => self::schedule($automation),
            'Targets' => self::targets($plan),
            'Steps' => self::steps($plan),
        ];

        foreach ($facts as $label => $value) {
            if ($value === '') {
                continue;
            }

            foreach (Str::wrap($value, $width) as $index => $line) {
                $this->out()->formatted(sprintf('  <fg=gray>%-9s</> %s', $index === 0 ? $label : '', OutputFormatter::escape($line)));
            }
        }

        $this->out()->line('');
    }

    /**
     * @param  array<string, mixed>  $automation
     */
    private static function schedule(array $automation): string
    {
        $triggers = ListCommand::triggers($automation);
        $timezone = Arr::get($automation, 'triggers.timezone');
        $last = Arr::get($automation, 'last_triggered_at');

        if (is_string(Arr::get($automation, 'triggers.cron')) && is_string($timezone) && $timezone !== '') {
            $triggers .= ' ('.$timezone.')';
        }

        return trim($triggers.(is_string($last) ? ' · last run '.RelativeTime::ago($last) : ''));
    }

    /**
     * "6 servers · web-01, db-01": how many of what, then their names.
     *
     * @param  array<string, mixed>  $plan
     */
    private static function targets(array $plan): string
    {
        $names = [];
        $types = [];

        foreach (is_array($plan['targets'] ?? null) ? $plan['targets'] : [] as $target) {
            if (! is_array($target)) {
                continue;
            }

            $names[] = Str::scalar($target['name'] ?? null, '#'.Str::scalar($target['id'] ?? null, '?'));
            $type = str_replace(['managed_', '_'], ['', ' '], Str::scalar($target['type'] ?? null, 'resource'));
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        if ($names === []) {
            return '';
        }

        $counts = [];

        foreach ($types as $type => $count) {
            $counts[] = $count.' '.($count === 1 ? $type : $type.'s');
        }

        return implode(', ', $counts).' · '.implode(', ', $names);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private static function steps(array $plan): string
    {
        $labels = [];

        foreach (is_array($plan['steps'] ?? null) ? $plan['steps'] : [] as $step) {
            if (is_array($step)) {
                $labels[] = Str::scalar($step['label'] ?? $step['slug'] ?? null, '?');
            }
        }

        return $labels === [] ? '' : count($labels).' · '.implode(', ', $labels);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function preview(array $plan): ExitCode
    {
        if ($this->structured()) {
            $this->out()->record($plan);

            return ExitCode::Ok;
        }

        $steps = [];

        foreach (is_array($plan['steps'] ?? null) ? $plan['steps'] : [] as $step) {
            if (is_array($step)) {
                $steps[] = $step;
            }
        }

        $this->out()->list($steps, ['position' => '#', 'label' => 'Step'], null, 'This recipe has no step.');

        $targets = [];

        foreach (is_array($plan['targets'] ?? null) ? $plan['targets'] : [] as $target) {
            if (is_array($target)) {
                $targets[] = $target;
            }
        }

        if ($targets !== []) {
            $this->out()->line('');
            $this->out()->list($targets, ['type' => 'Type', 'id' => 'Id', 'name' => 'Name']);
        }

        $rejection = $plan['rejection'] ?? null;

        if (is_string($rejection) && $rejection !== '') {
            $this->out()->warn($rejection);

            return ExitCode::RemoteFailure;
        }

        return ExitCode::Ok;
    }
}
