<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Incident;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Incidents\ShowIncident;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'incident:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one incident');
    }

    protected function define(): void
    {
        $this->addArgument('incident', InputArgument::REQUIRED, 'Incident id');
    }

    public function examples(): array
    {
        return [
            'One incident' => 'unolia incident view 77',
            'What deployment started it' => 'unolia incident view 77 --json origin_deployment',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $incident = $this->fetch(new ShowIncident((string) $this->argumentString('incident')));

        if ($this->structured()) {
            $this->out()->record($incident);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $incident['id'] ?? null,
            'kind' => $incident['kind'] ?? null,
            'status' => $incident['status'] ?? null,
            'severity' => $incident['severity'] ?? null,
            'resolution' => $incident['resolution'] ?? null,
            'website' => Arr::get($incident, 'website.domain'),
            'project' => Arr::get($incident, 'project.name'),
            'check_type' => $incident['check_type'] ?? null,
            'started_at' => RelativeTime::ago(is_string($incident['started_at'] ?? null) ? $incident['started_at'] : null),
            'duration_seconds' => RelativeTime::duration(is_numeric($incident['duration_seconds'] ?? null) ? (int) $incident['duration_seconds'] : null),
            'origin_deployment' => Arr::get($incident, 'origin_deployment.id'),
            'fixing_deployment' => Arr::get($incident, 'fixing_deployment.id'),
            'origin_confidence' => $incident['origin_confidence'] ?? null,
            'url' => $incident['url'] ?? null,
        ], [
            'id' => 'Id',
            'kind' => 'Kind',
            'status' => 'Status',
            'severity' => 'Severity',
            'resolution' => 'Resolution',
            'website' => 'Website',
            'project' => 'Project',
            'check_type' => 'Check',
            'started_at' => 'Started',
            'duration_seconds' => 'Duration',
            'origin_deployment' => 'Origin deployment',
            'fixing_deployment' => 'Fixing deployment',
            'origin_confidence' => 'Origin confidence',
            'url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
