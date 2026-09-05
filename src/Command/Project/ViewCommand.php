<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Project;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Projects\ProjectEnvironments;
use Unolia\Cli\Api\Requests\Projects\ShowProject;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

final class ViewCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'project:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one project and its environments');
    }

    protected function define(): void
    {
        $this->addArgument('project', InputArgument::OPTIONAL, 'Project id or name, the linked one by default');
    }

    public function examples(): array
    {
        return [
            'The linked project' => 'unolia project view',
            'Another one' => 'unolia project view 12',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->projectId();
        $project = $this->fetch(new ShowProject($id));
        $environments = $this->collection(new ProjectEnvironments($id));

        if ($this->structured()) {
            $this->out()->record($project + ['environments' => $environments]);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $project['id'] ?? null,
            'name' => $project['name'] ?? null,
            'domain' => $project['domain'] ?? null,
            'status' => $project['status_label'] ?? ($project['status'] ?? null),
            'team' => Arr::get($project, 'team.slug'),
            'websites' => Arr::get($project, 'counts.websites'),
            'open_issues' => Arr::get($project, 'counts.open_issues'),
            'url' => $project['url'] ?? null,
        ], [
            'id' => 'Id',
            'name' => 'Name',
            'domain' => 'Domain',
            'status' => 'Status',
            'team' => 'Team',
            'websites' => 'Websites',
            'open_issues' => 'Open issues',
            'url' => 'Url',
        ]);

        if ($environments !== []) {
            $this->out()->line('');
            $this->out()->list(
                $environments,
                ['id' => 'Id', 'name' => 'Environment', 'anchor' => 'Anchor', 'resources' => 'Resources'],
                static fn (array $row): array => [
                    'anchor' => Str::scalar(Arr::get($row, 'anchor.name')),
                    'resources' => Str::scalar(is_array($row['resources'] ?? null) ? count($row['resources']) : 0),
                ],
            );
        }

        return ExitCode::Ok;
    }
}
