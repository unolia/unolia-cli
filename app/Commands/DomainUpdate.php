<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\DomainsRecordsShow;
use App\Http\Integrations\Unolia\Requests\DomainsRecordsUpdate;
use LaravelZero\Framework\Commands\Command;

class DomainUpdate extends Command
{
    protected $signature = 'domain:update {domain} {record_id} {name} {value?} {--ttl=}';

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

        $response = $connector->send(new DomainsRecordsUpdate(
            record: $record['id'],
            name: $this->argument('name') ?: $record['name'],
            value: $this->argument('value') ?: $record['value'],
        ));

        if ($response->successful()) {
            $this->info('Record updated successfully');
        } else {
            $this->error('Failed to update record: '.($response->json('message') ?: 'Unknown error'));
        }
    }
}
