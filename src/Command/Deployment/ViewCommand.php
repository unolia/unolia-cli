<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Deployment;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Deployments\ShowDeployment;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'deployment:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one deployment');
    }

    protected function define(): void
    {
        $this->addArgument('deployment', InputArgument::REQUIRED, 'Deployment id');
    }

    public function examples(): array
    {
        return [
            'One deployment' => 'unolia deployment view 4812',
            'Its status only' => 'unolia deployment view 4812 --json status',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $deployment = $this->fetch(new ShowDeployment((string) $this->argumentString('deployment')));

        if ($this->structured()) {
            $this->out()->record($deployment);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $deployment['id'] ?? null,
            'status' => $deployment['status_label'] ?? ($deployment['status'] ?? null),
            'website' => Arr::get($deployment, 'website.domain'),
            'branch' => Arr::get($deployment, 'commit.branch'),
            'commit' => Arr::get($deployment, 'commit.short'),
            'message' => Arr::get($deployment, 'commit.message'),
            'triggered_by' => Arr::get($deployment, 'triggered_by.name'),
            'started_at' => RelativeTime::ago(is_string($deployment['started_at'] ?? null) ? $deployment['started_at'] : null),
            'duration_seconds' => RelativeTime::duration(is_numeric($deployment['duration_seconds'] ?? null) ? (int) $deployment['duration_seconds'] : null),
            'url' => $deployment['url'] ?? null,
        ], [
            'id' => 'Id',
            'status' => 'Status',
            'website' => 'Website',
            'branch' => 'Branch',
            'commit' => 'Commit',
            'message' => 'Message',
            'triggered_by' => 'Triggered by',
            'started_at' => 'Started',
            'duration_seconds' => 'Duration',
            'url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
