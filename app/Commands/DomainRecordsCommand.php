<?php

namespace Unolia\UnoliaCLI\Commands;

use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\DomainsRecords;

class DomainRecordsCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'domain:records {domain}';

    protected $description = 'List all records for a domain';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

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
