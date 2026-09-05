<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Providers\ShowProvider;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'provider:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one connected provider');
    }

    protected function define(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Provider id');
    }

    public function examples(): array
    {
        return [
            'One provider' => 'unolia provider view 14',
            'Why it needs attention' => 'unolia provider view 14 --json attention_reasons',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $provider = $this->fetch(new ShowProvider((string) $this->argumentString('provider')));

        if ($this->structured()) {
            $this->out()->record($provider);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'id' => $provider['id'] ?? null,
            'name' => $provider['name'] ?? null,
            'provider' => $provider['provider_label'] ?? ($provider['provider'] ?? null),
            'category' => $provider['category'] ?? null,
            'state' => $provider['state'] ?? null,
            'expires_at' => RelativeTime::ago(is_string($provider['expires_at'] ?? null) ? $provider['expires_at'] : null, null, '-'),
            'sync_status' => $provider['sync_status'] ?? null,
            'synced_at' => RelativeTime::ago(is_string($provider['synced_at'] ?? null) ? $provider['synced_at'] : null, null, 'never synced'),
            'cost_sync_status' => $provider['cost_sync_status'] ?? null,
            'needs_attention' => $provider['needs_attention'] ?? null,
            'attention_reasons' => Str::scalar($provider['attention_reasons'] ?? null),
            'url' => $provider['url'] ?? null,
        ], [
            'id' => 'Id',
            'name' => 'Name',
            'provider' => 'Provider',
            'category' => 'Category',
            'state' => 'State',
            'expires_at' => 'Expires',
            'sync_status' => 'Sync',
            'synced_at' => 'Last synced',
            'cost_sync_status' => 'Cost sync',
            'needs_attention' => 'Attention',
            'attention_reasons' => 'Reasons',
            'url' => 'Url',
        ]);

        return ExitCode::Ok;
    }
}
