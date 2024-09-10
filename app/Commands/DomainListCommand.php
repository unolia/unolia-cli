<?php

namespace Unolia\UnoliaCLI\Commands;

use Carbon\Carbon;
use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\Domains;

use function Laravel\Prompts\table;

class DomainListCommand extends Command
{
    protected $signature = 'domain:list';

    protected $description = 'List all domains associated with the account';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

        $response = $connector->paginate(new Domains);

        $table = $response->collect()->map(fn ($domain) => [
            'Domain' => $domain['domain'],
            'Team' => $domain['team']['name'],
            'Last synced at' => $domain['synced_at'] ? Carbon::make($domain['synced_at'])->diffForHumans() : 'Never synced',
        ])->toArray();

        table(['Domain', 'Team', 'Last Synced At'], $table);
    }
}
