<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\DomainsRecordsDelete;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelZero\Framework\Commands\Command;

class DomainRemove extends Command implements PromptsForMissingInput
{
    protected $signature = 'domain:remove {record_id}';

    protected $description = 'Remove a record from a domain';

    public function handle()
    {
        $connector = Helpers::connector();

        $response = $connector->send(new DomainsRecordsDelete(
            record: $this->argument('record_id'))
        );

        if ($response->successful()) {
            $this->info('Record removed successfully');
        } else {
            $this->error('Failed to remove record: '.($response->json('message') ?: 'Unknown error'));
        }
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'record_id' => [
                'Enter the record id',
                'Ex: 1',
            ],
        ];
    }
}
