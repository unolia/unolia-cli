<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Env;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Environments\ShowEnvironment;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'env:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one environment');
    }

    protected function define(): void
    {
        $this->addArgument('environment', InputArgument::REQUIRED, 'Environment id');
    }

    public function examples(): array
    {
        return [
            'One environment' => 'unolia env view 41',
            'Its resources' => 'unolia env view 41 --json resources',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $environment = $this->fetch(new ShowEnvironment((string) $this->argumentString('environment')));

        if ($this->structured()) {
            $this->out()->record($environment);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $environment['id'] ?? null,
            'name' => $environment['name'] ?? null,
            'project' => Arr::get($environment, 'project.name'),
            'anchor' => Arr::get($environment, 'anchor.name'),
            'last_computed_at' => RelativeTime::ago(is_string($environment['last_computed_at'] ?? null) ? $environment['last_computed_at'] : null),
            'url' => $environment['url'] ?? null,
        ], [
            'id' => 'Id',
            'name' => 'Name',
            'project' => 'Project',
            'anchor' => 'Anchor',
            'last_computed_at' => 'Last computed',
            'url' => 'Url',
        ]);

        $resources = [];

        foreach (is_array($environment['resources'] ?? null) ? $environment['resources'] : [] as $resource) {
            if (is_array($resource)) {
                $resources[] = $resource;
            }
        }

        if ($resources !== []) {
            $this->out()->line('');
            $this->out()->list(
                $resources,
                ['label' => 'Role', 'name' => 'Name', 'id' => 'Id', 'dns_state' => 'DNS'],
                static fn (array $row): array => ['dns_state' => Str::scalar($row['dns_state'] ?? null)],
            );
        }

        return ExitCode::Ok;
    }
}
