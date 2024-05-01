<?php

namespace Unolia\UnoliaCLI\Commands;

use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\DomainsRecordsCreate;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class DomainAddCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'domain:add {domain} {name} {type} {value} {--ttl=}';

    protected $description = 'Add a new record to a domain';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

        $response = $connector->send(new DomainsRecordsCreate(
            domain: $this->argument('domain'),
            name: $this->argument('name'),
            type: $this->argument('type'),
            value: $this->argument('value'),
            ttl: is_numeric($this->option('ttl')) ? (int) $this->option('ttl') : null
        ));

        if ($response->successful()) {
            $this->info('Record created successfully');
        } else {
            $this->error('Failed to create record: '.($response->json('message') ?: 'Unknown error'));
        }
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'domain' => fn () => text(
                label: 'Domain name',
                placeholder: 'E.g. example.com',
                default: ($this->argument('domain') ?: ''),
                hint: 'Zone domain you want to add the record to. (Use domains:list to get the list of available domains)'
            ),
            'name' => fn () => text(
                label: 'Full domain name',
                placeholder: 'E.g. email.example.com',
                default: ($this->argument('domain') ?: ''),
                hint: 'Put the full subdomain. Use punycode for non-ASCII characters'
            ),
            'type' => fn () => select(
                label: 'Record type',
                options: [
                    'A', 'AAAA', 'CNAME', 'DNAME', 'MX', 'NS', 'PTR', 'SOA', 'SRV', 'TXT', 'DKIM', 'SPF', 'DMARC',
                    'BIMI',
                ],
                default: ($this->argument('type') ?: ''),
            ),
            'value' => fn () => text(
                label: 'Value',
                default: ($this->argument('value') ?: ''),
                hint: match ($this->argument('type')) {
                    'A' => 'Put an IPv4 address',
                    'AAAA' => 'Put an IPv6 address',
                    'CNAME', 'DNAME', 'NS' => 'Put a fully qualified domain name ending with a dot',
                    'MX' => 'Put the mail server domain name with a priority in the format "10 mail.example.com."',
                    'TXT' => 'Put the text value',
                    default => null,
                },
            ),
            'ttl' => [
                'Enter the record TTL',
                'Ex: 3600',
            ],
        ];
    }
}
