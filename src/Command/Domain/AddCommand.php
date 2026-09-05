<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\CreateRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;

/**
 * Add a DNS record. Adding is reversible, so it asks nothing beyond the missing values.
 */
final class AddCommand extends BaseCommand
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV', 'CAA', 'PTR'];

    protected function canonical(): string
    {
        return 'domain:add';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Add a record to a domain');
    }

    protected function define(): void
    {
        $this->addArgument('domain', InputArgument::OPTIONAL, 'The domain name');
        $this->addArgument('name', InputArgument::OPTIONAL, 'The full record name, @ for the domain itself');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type');
        $this->addArgument('value', InputArgument::OPTIONAL, 'Record value');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Time to live in seconds');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Point a subdomain' => 'unolia domain add acme.com www.acme.com A 203.0.113.10',
            'Publish a TXT record' => 'unolia domain add acme.com _dmarc.acme.com TXT "v=DMARC1; p=none"',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $domain = $this->argumentString('domain')
            ?? $this->ask()->text('Domain name', '<domain>', 'example.com', '', 'The zone the record goes into');

        $name = $this->argumentString('name')
            ?? $this->ask()->text('Full record name', '<name>', 'www.'.$domain, $domain, 'Use punycode for non ASCII names');

        if ($name === '@') {
            $name = $domain;
        }

        $type = strtoupper($this->argumentString('type')
            ?? $this->ask()->select('Record type', array_combine(self::TYPES, self::TYPES), '<type>', 'A'));

        $value = $this->argumentString('value')
            ?? $this->ask()->text('Value', '<value>', '', '', $this->hintFor($type));

        $body = Arr::filled([
            'name' => $name,
            'type' => $type,
            'value' => $value,
            'ttl' => $this->optionInt('ttl'),
        ]);

        if ($this->dryRun()) {
            $this->out()->record(['domain' => $domain] + $body);

            return ExitCode::Ok;
        }

        $record = $this->fetch(new CreateRecord($domain, $body));

        if ($this->structured()) {
            $this->out()->record($record);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf(
            'Added %s %s %s',
            (string) ($record['name'] ?? $name),
            (string) ($record['type'] ?? $type),
            (string) ($record['value'] ?? $value),
        ));

        if (is_numeric($record['id'] ?? null)) {
            $this->out()->note(sprintf('Follow it with unolia watch record %d', (int) $record['id']));
        }

        return ExitCode::Ok;
    }

    private function hintFor(string $type): string
    {
        return match ($type) {
            'A' => 'An IPv4 address',
            'AAAA' => 'An IPv6 address',
            'CNAME', 'NS' => 'A fully qualified domain name ending with a dot',
            'MX' => 'A priority and a mail server, such as "10 mail.example.com."',
            'TXT' => 'The text value',
            default => '',
        };
    }
}
