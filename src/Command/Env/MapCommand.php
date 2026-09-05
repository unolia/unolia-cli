<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Env;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Projects\ProjectEnvironments;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * The environment map of a project: what each environment is made of.
 */
final class MapCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'env:map';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show the environment map of a project');
    }

    public function examples(): array
    {
        return [
            'The map here' => 'unolia env map',
            'For a script' => 'unolia env map --json name,resources',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $environments = $this->collection(new ProjectEnvironments($this->projectId()));

        if ($this->structured()) {
            $this->out()->list($environments, ['id' => 'Id', 'name' => 'Name']);

            return ExitCode::Ok;
        }

        if ($environments === []) {
            $this->out()->note('This project has no environment yet.');

            return ExitCode::Ok;
        }

        foreach ($environments as $index => $environment) {
            if ($index > 0) {
                $this->out()->line('');
            }

            $this->out()->line(sprintf(
                '%s  (anchor %s)',
                (string) ($environment['name'] ?? ''),
                (string) (Arr::get($environment, 'anchor.name') ?? 'none'),
            ));

            $resources = [];

            foreach (is_array($environment['resources'] ?? null) ? $environment['resources'] : [] as $resource) {
                if (is_array($resource)) {
                    $resources[] = $resource;
                }
            }

            $this->out()->list(
                $resources,
                ['label' => 'Role', 'name' => 'Name', 'id' => 'Id', 'dns_state' => 'DNS'],
                static fn (array $row): array => ['dns_state' => Str::scalar($row['dns_state'] ?? null)],
                'No resources.',
            );
        }

        return ExitCode::Ok;
    }
}
