<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\Teams;

use function Laravel\Prompts\table;

class TeamsCommand extends Command
{
    protected $signature = 'teams';

    protected $description = 'List all teams associated with this token';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

        $response = $connector->send(new Teams());

        $table = $response->collect('data')->map(fn ($team) => [
            'ID' => $team['id'],
            'Team' => $team['name'],
        ])->toArray();

        table(['ID', 'Team'], $table);
    }
}
