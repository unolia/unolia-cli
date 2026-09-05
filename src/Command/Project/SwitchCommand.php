<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Project;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Projects\ShowProject;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Support\Arr;

/**
 * Point this directory at another project, dropping a website that belongs elsewhere.
 */
final class SwitchCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'project:switch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Point this directory at another project');
    }

    protected function define(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED, 'Project id or name');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Switch project' => 'unolia project switch 12',
            'See what would change' => 'unolia project switch 12 --dry-run',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = $this->runtime()->context()->projectId((string) $this->argumentString('project'));
        $project = $this->fetch(new ShowProject($id));

        $config = $this->runtime()->context()->config();
        $root = $config->rootDir() ?? $this->runtime()->context()->git()->root();

        if ($root === null) {
            throw CliError::usage(
                'there is no .unolia/config.json here',
                'Run unolia init first.',
            );
        }

        $data = $config->data;
        $data['project'] = $id;
        $team = Arr::get($project, 'team.slug');

        if (is_string($team) && $team !== '') {
            $data['team'] = $team;
        }

        $dropped = $this->dropForeignWebsite($data, $id);

        if ($this->dryRun()) {
            $this->out()->record(['project' => $id, 'path' => $config->path ?? $root.'/'.ProjectConfig::FILE, 'dropped_website' => $dropped]);

            return ExitCode::Ok;
        }

        $path = ProjectConfig::write($root, $data);

        if ($this->structured()) {
            $this->out()->record(['project' => $id, 'path' => $path, 'dropped_website' => $dropped]);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('This directory now points at project %d %s', $id, (string) ($project['name'] ?? '')));

        if ($dropped) {
            $this->out()->note('The linked website belonged to another project, so it was removed. Run unolia init to link one.');
        }

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dropForeignWebsite(array &$data, int $project): bool
    {
        $website = $data['website'] ?? null;

        if (! is_numeric($website)) {
            return false;
        }

        $record = $this->fetch(new ShowWebsite((int) $website));

        if ((int) (Arr::get($record, 'project.id') ?? 0) === $project) {
            return false;
        }

        unset($data['website'], $data['environments']);

        return true;
    }
}
