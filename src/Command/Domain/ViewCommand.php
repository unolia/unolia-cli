<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Domains\ShowDomain;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ViewCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one domain');
    }

    protected function define(): void
    {
        $this->addArgument('domain', InputArgument::OPTIONAL, 'The domain name');
    }

    public function examples(): array
    {
        return [
            'One domain' => 'unolia domain view acme.com',
            'Its nameservers' => 'unolia domain view acme.com --json ns',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $domain = $this->argumentString('domain')
            ?? $this->ask()->text('Domain name', '<domain>', 'example.com');

        $record = $this->fetch(new ShowDomain($domain));

        if ($this->structured()) {
            $this->out()->record($record);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'domain' => $record['domain'] ?? null,
            'team' => Arr::get($record, 'team.name') ?? Arr::get($record, 'team.slug'),
            'nameservers' => $record['ns'] ?? ($record['nameservers'] ?? null),
            'synced_at' => RelativeTime::ago(is_string($record['synced_at'] ?? null) ? $record['synced_at'] : null, null, 'never synced'),
        ], [
            'domain' => 'Domain',
            'team' => 'Team',
            'nameservers' => 'Nameservers',
            'synced_at' => 'Last synced',
        ]);

        return ExitCode::Ok;
    }
}
