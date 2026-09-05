<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Websites\WebsiteDomains;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

final class DomainsCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'website:domains';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the domains of a website');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
    }

    public function examples(): array
    {
        return [
            'Domains of this website' => 'unolia website domains',
            'SSL status only' => 'unolia website domains --json domain,ssl_status',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new WebsiteDomains($this->websiteId(), $this->listQuery()));

        $this->out()->list(
            $rows,
            [
                'domain' => 'Domain',
                'kind' => 'Kind',
                'ssl_status' => 'SSL',
                'ssl_expires_at' => 'SSL expires',
                'dns_resolved_ipv4' => 'DNS',
            ],
            static fn (array $row): array => [
                'ssl_expires_at' => RelativeTime::ago(is_string($row['ssl_expires_at'] ?? null) ? $row['ssl_expires_at'] : null, null, '-'),
                'dns_resolved_ipv4' => Str::scalar($row['dns_resolved_ipv4'] ?? null),
            ],
            'No domains on this website.',
        );

        return ExitCode::Ok;
    }
}
