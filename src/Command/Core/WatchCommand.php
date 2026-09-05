<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Repositories\ListRepositoryActions;
use Unolia\Cli\Api\Requests\Websites\ListWebsiteDeployments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Watch\ActionTarget;
use Unolia\Cli\Watch\AutomationRunTarget;
use Unolia\Cli\Watch\DeploymentTarget;
use Unolia\Cli\Watch\RecordTarget;
use Unolia\Cli\Watch\Target;

/**
 * Follow whatever this directory started, or anything named on the command line.
 */
final class WatchCommand extends BaseCommand
{
    use ResolvesTargets;
    use Watches;

    private const KINDS = ['deployment', 'ci', 'automation', 'record'];

    protected function canonical(): string
    {
        return 'watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Follow a deployment, CI run, automation run or DNS record');
    }

    protected function define(): void
    {
        $this->addArgument('kind', InputArgument::OPTIONAL, 'deployment, ci, automation or record');
        $this->addArgument('id', InputArgument::OPTIONAL, 'The id of the thing to watch');
        $this->addWatchOptions();
    }

    public function examples(): array
    {
        return [
            'Whatever this directory started' => 'unolia watch',
            'A deployment' => 'unolia watch deployment 4812',
            'A DNS record' => 'unolia watch record 88231',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $kind = $this->argumentString('kind');
        $id = $this->argumentString('id');

        $target = $kind === null
            ? $this->newestHere()
            : $this->targetFor($kind, $id);

        $result = $this->follow($target);

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }

    private function targetFor(string $kind, ?string $id): Target
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw CliError::usage(
                sprintf('%s is not something to watch', $kind),
                'Watch one of: '.implode(', ', self::KINDS),
                ['candidates' => self::KINDS],
            );
        }

        if ($id === null) {
            throw CliError::missingInput(sprintf('the %s id', $kind));
        }

        return match ($kind) {
            'deployment' => $this->deployment((int) $id),
            'ci' => $this->action((int) $id),
            'automation' => new AutomationRunTarget($this->api(), $id, $this->waitSeconds()),
            default => new RecordTarget($this->api(), (int) $id),
        };
    }

    private function deployment(int $id): Target
    {
        $this->local()->remember('last_deployment', $id);

        return new DeploymentTarget($this->api(), $id, $this->waitSeconds());
    }

    private function action(int $id): Target
    {
        $this->local()->remember('last_ci_run', $id);

        return new ActionTarget($this->api(), $id, $this->waitSeconds());
    }

    /**
     * Local state first, then the newest deployment and the newest CI run of this directory.
     */
    private function newestHere(): Target
    {
        $local = $this->local();
        $deployment = $local->get('last_deployment');
        $ciRun = $local->get('last_ci_run');

        if (is_numeric($deployment) || is_numeric($ciRun)) {
            $deploymentAt = $this->age($local->get('last_deployment_at'));
            $ciAt = $this->age($local->get('last_ci_run_at'));

            if (is_numeric($deployment) && (! is_numeric($ciRun) || $deploymentAt >= $ciAt)) {
                return new DeploymentTarget($this->api(), (int) $deployment, $this->waitSeconds());
            }

            return new ActionTarget($this->api(), (int) $ciRun, $this->waitSeconds());
        }

        $context = $this->context(Need::None);

        if ($context->website === null) {
            throw CliError::notFound(
                'nothing to watch here yet',
                'Run unolia init to link this directory, or name what to watch: unolia watch deployment 4812.',
            );
        }

        $latestDeployment = $this->latestDeployment($context->website);
        $latestRun = $this->latestRun();

        if ($latestDeployment === null && $latestRun === null) {
            throw CliError::notFound(
                'nothing to watch here yet',
                'Start something with unolia deploy, or name what to watch.',
            );
        }

        if ($latestRun === null || ($latestDeployment !== null && $latestDeployment['at'] >= $latestRun['at'])) {
            /** @var array{id: int, at: int} $latestDeployment */
            return $this->deployment($latestDeployment['id']);
        }

        return $this->action($latestRun['id']);
    }

    /**
     * @return array{id: int, at: int}|null
     */
    private function latestDeployment(int $website): ?array
    {
        $rows = $this->collection(new ListWebsiteDeployments($website, ['per_page' => 1]));
        $latest = $rows[0] ?? null;

        if ($latest === null || ! is_numeric($latest['id'] ?? null)) {
            return null;
        }

        return [
            'id' => (int) $latest['id'],
            'at' => RelativeTime::parse(is_string($latest['started_at'] ?? null) ? $latest['started_at'] : null)?->getTimestamp() ?? 0,
        ];
    }

    /**
     * @return array{id: int, at: int}|null
     */
    private function latestRun(): ?array
    {
        try {
            $repository = $this->repositoryId();
        } catch (CliError|ApiException) {
            return null;
        }

        $branch = $this->runtime()->context()->git()->branch();
        $rows = $this->collection(new ListRepositoryActions($repository, array_filter([
            'branch' => $branch,
            'per_page' => 1,
        ], static fn (mixed $value): bool => $value !== null)));

        $latest = $rows[0] ?? null;

        if ($latest === null || ! is_numeric($latest['id'] ?? null)) {
            return null;
        }

        return [
            'id' => (int) $latest['id'],
            'at' => RelativeTime::parse(is_string($latest['started_at'] ?? null) ? $latest['started_at'] : null)?->getTimestamp() ?? 0,
        ];
    }

    private function age(mixed $timestamp): int
    {
        return RelativeTime::parse(is_string($timestamp) ? $timestamp : null)?->getTimestamp() ?? 0;
    }
}
