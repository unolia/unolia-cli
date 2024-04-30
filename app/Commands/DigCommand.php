<?php

namespace App\Commands;

use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelZero\Framework\Commands\Command;
use LibDNS\Records\ResourceTypes;

use function Laravel\Prompts\select;

class DigCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'dig {domain} {type} {--server=1.1.1.1}';

    protected $description = 'Query DNS server for informations about a domain';

    public function handle()
    {
        $domain = $this->argument('domain');
        $type = $this->argument('type');
        $server = $this->option('server');

        $type = match (strtoupper($type)) {
            'A' => ResourceTypes::A,
            'AAAA' => ResourceTypes::AAAA,
            'CNAME' => ResourceTypes::CNAME,
            'MX' => ResourceTypes::MX,
            'NS' => ResourceTypes::NS,
            'PTR' => ResourceTypes::PTR,
            'SOA' => ResourceTypes::SOA,
            'SRV' => ResourceTypes::SRV,
            'TXT' => ResourceTypes::TXT,
            default => throw new \InvalidArgumentException("Invalid record type: $type"),
        };

        try {
            $records = \Amp\Dns\query($domain, $type);
        } catch (DnsException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $table = collect($records)->map(fn ($record) => [
            'name' => $domain,
            'type' => DnsRecord::getName($record->getType()),
            'ttl' => $record->getTtl(),
            'value' => $record->getValue(),
        ])->toArray();

        $this->table(['Name', 'Type', 'TTL', 'Value'], $table);
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'domain' => [
                'Enter the domain name',
                'example.com',
            ],
            'type' => fn () => select(
                'Select the record type',
                [
                    'A',
                    'AAAA',
                    'CNAME',
                    'MX',
                    'NS',
                    'PTR',
                    'SOA',
                    'SRV',
                    'TXT',
                ],
                'A'
            ),
        ];
    }
}
