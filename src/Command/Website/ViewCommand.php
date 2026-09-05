<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Websites\ShowWebsite;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'website:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one website');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
    }

    public function examples(): array
    {
        return [
            'The linked website' => 'unolia website view',
            'Another one' => 'unolia website view marketing.acme.com',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $website = $this->fetch(new ShowWebsite($this->websiteId()));

        if ($this->structured()) {
            $this->out()->record($website);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $website['id'] ?? null,
            'domain' => $website['domain'] ?? null,
            'status' => $website['status'] ?? null,
            'health' => $website['health_label'] ?? ($website['health'] ?? null),
            'provider' => Arr::get($website, 'provider.label') ?? Arr::get($website, 'provider.slug'),
            'project' => Arr::get($website, 'project.name'),
            'server' => Arr::get($website, 'managed_server.name'),
            'php_version' => $website['php_version'] ?? null,
            'repository' => Arr::get($website, 'repository.full_name'),
            'branch' => Arr::get($website, 'repository.branch'),
            'deployed_commit' => $website['deployed_commit'] ?? null,
            'last_deployed_at' => RelativeTime::ago(is_string($website['last_deployed_at'] ?? null) ? $website['last_deployed_at'] : null),
            'url' => $website['url'] ?? null,
        ], [
            'id' => 'Id',
            'domain' => 'Domain',
            'status' => 'Status',
            'health' => 'Health',
            'provider' => 'Provider',
            'project' => 'Project',
            'server' => 'Server',
            'php_version' => 'PHP',
            'repository' => 'Repository',
            'branch' => 'Branch',
            'deployed_commit' => 'Commit',
            'last_deployed_at' => 'Last deploy',
            'url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
