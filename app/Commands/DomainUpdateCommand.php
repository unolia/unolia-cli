<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\DomainsRecordsShow;
use App\Http\Integrations\Unolia\Requests\DomainsRecordsUpdate;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class DomainUpdateCommand extends Command
{
    protected $signature = 'domain:update {record_id} {name?} {value?} {--ttl=}';

    protected $description = 'Update a record in a domain';

    public function handle()
    {
        $connector = Helpers::connector();

        $response = $connector->send(new DomainsRecordsShow(
            record: $this->argument('record_id')
        ));

        if ($response->failed()) {
            $this->error('Failed to fetch record: '.($response->json('message') ?: 'Unknown error'));

            return;
        }

        $record = $response->json('data');

        if (! $this->argument('name') && ! $this->argument('value')) {
            $this->line(''); // Blanc line
            $this->info('  Updating the following record: '.implode(' | ', [
                $record['name'],
                $record['type'],
                $record['value'],
            ]));

            $name = text(
                label: 'Full domain name',
                placeholder: 'E.g. email.example.com',
                default: $record['name'],
                hint: 'Put the full subdomain. Use punycode for non-ASCII characters'
            );

            $hint = match ($record['type']) {
                'A' => 'Put an IPv4 address',
                'AAAA' => 'Put an IPv6 address',
                'CNAME', 'DNAME', 'NS' => 'Put a fully qualified domain name ending with a dot',
                'MX' => 'Put the mail server domain name with a priority in the format "10 mail.example.com."',
                'TXT' => 'Put the text value',
                default => null,
            };

            $value = text(
                label: 'Value',
                default: $record['value'],
                hint: $hint,
            );
        }

        $name = $name ?? ($this->argument('name') ?: $record['name']);
        $value = $value ?? ($this->argument('value') ?: $record['value']);

        $response = $connector->send(new DomainsRecordsUpdate(
            record: $record['id'],
            name: $name,
            value: $value,
            ttl: is_numeric($this->option('ttl')) ? (int) $this->option('ttl') : null
        ));

        if ($response->successful()) {
            $this->info('Record updated successfully');
        } else {
            $this->error('Failed to update record: '.($response->json('message') ?: 'Unknown error'));
        }
    }
}
