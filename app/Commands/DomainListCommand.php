<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\Domains;
use Carbon\Carbon;
use LaravelZero\Framework\Commands\Command;

class DomainListCommand extends Command
{
    protected $signature = 'domain:list';

    protected $description = 'List all domains associated with the account';

    public function handle()
    {
        $connector = Helpers::connector();

        $response = $connector->paginate(new Domains());

        $table = $response->collect()->map(fn ($domain) => [
            'Domain' => $domain['domain'],
            'Team' => $domain['team']['name'],
            'Last synced at' => $domain['synced_at'] ? Carbon::make($domain['synced_at'])->diffForHumans() : 'Never synced',
        ])->toArray();

        $this->table(['Domain', 'Team', 'Last Synced At'], $table);
    }
}
