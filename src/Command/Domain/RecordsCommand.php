<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;

final class RecordsCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:records';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the records of a domain');
    }

    protected function define(): void
    {
        $this->addArgument('domain', InputArgument::OPTIONAL, 'The domain name');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only records of this type');
    }

    public function examples(): array
    {
        return [
            'Every record' => 'unolia domain records acme.com',
            'TXT records only' => 'unolia domain records acme.com --type TXT',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $domain = $this->argumentString('domain')
            ?? $this->ask()->text('Domain name', '<domain>', 'example.com');

        $rows = $this->rows(new ListDomainRecords($domain, $this->listQuery()));
        $type = $this->optionString('type');

        if ($type !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => strcasecmp((string) ($row['type'] ?? ''), $type) === 0,
            ));
        }

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'name' => 'Name',
                'type' => 'Type',
                'ttl' => 'TTL',
                'value' => 'Value',
                'state' => 'State',
            ],
            static fn (array $row): array => [
                'ttl' => Str::scalar($row['ttl'] ?? null, '---'),
                'value' => Str::limit(is_string($row['value'] ?? null) ? $row['value'] : '', 40),
            ],
            'This domain has no records.',
        );

        return ExitCode::Ok;
    }
}
