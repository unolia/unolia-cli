<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\DomainsRecords;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class DomainRecordsCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'domain:records {domain}';

    protected $description = 'List all records for a domain';

    public function handle()
    {
        $connector = Helpers::connector();

        $response = $connector->paginate(new DomainsRecords($this->argument('domain')));

        $table = $response->collect()->map(fn ($record) => [
            '#' => $record['id'],
            'name' => $record['name'],
            'type' => $record['type'],
            'ttl' => $record['ttl'],
            'value' => Str::limit($record['value'], 20),
            'state' => $record['state'],
        ])->toArray();

        $this->table(['#', 'Name', 'Type', 'TTL', 'Value', 'State'], $table);
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'domain' => [
                'Enter the domain name',
                'example.com',
            ],
        ];
    }
}
