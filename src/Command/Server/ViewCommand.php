<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Server;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Servers\ShowServer;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'server:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one managed server');
    }

    protected function define(): void
    {
        $this->addArgument('server', InputArgument::REQUIRED, 'Server id');
    }

    public function examples(): array
    {
        return [
            'One server' => 'unolia server view 61',
            'What it runs' => 'unolia server view 61 --json php_version,database',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $server = $this->fetch(new ShowServer((string) $this->argumentString('server')));

        if ($this->structured()) {
            $this->out()->record($server);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $server['id'] ?? null,
            'name' => $server['name'] ?? null,
            'provider' => Arr::get($server, 'provider.slug'),
            'type' => $server['type'] ?? null,
            'public_ipv4' => $server['public_ipv4'] ?? null,
            'status' => $server['status'] ?? null,
            'php_version' => $server['php_version'] ?? null,
            'database' => trim((string) (Arr::get($server, 'database.engine') ?? '').' '.(string) (Arr::get($server, 'database.version') ?? '')),
            'ubuntu_version' => $server['ubuntu_version'] ?? null,
            'synced_at' => RelativeTime::ago(is_string($server['synced_at'] ?? null) ? $server['synced_at'] : null),
            'url' => $server['url'] ?? null,
        ], [
            'id' => 'Id',
            'name' => 'Name',
            'provider' => 'Provider',
            'type' => 'Type',
            'public_ipv4' => 'IP',
            'status' => 'Status',
            'php_version' => 'PHP',
            'database' => 'Database',
            'ubuntu_version' => 'Ubuntu',
            'synced_at' => 'Last synced',
            'url' => 'Url',
        ]);

        $websites = [];

        foreach (is_array($server['websites'] ?? null) ? $server['websites'] : [] as $website) {
            if (is_array($website)) {
                $websites[] = $website;
            }
        }

        if ($websites !== []) {
            $this->out()->line('');
            $this->out()->list($websites, ['id' => 'Id', 'domain' => 'Website']);
        }

        return ExitCode::Ok;
    }
}
