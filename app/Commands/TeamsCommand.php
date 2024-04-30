<?php

namespace App\Commands;

use App\Helpers;
use App\Http\Integrations\Unolia\Requests\Teams;
use LaravelZero\Framework\Commands\Command;

class TeamsCommand extends Command
{
    protected $signature = 'teams';

    protected $description = 'List all teams associated with this token';

    public function handle()
    {
        $connector = Helpers::connector();

        $response = $connector->send(new Teams());

        $table = $response->collect('data')->map(fn ($team) => [
            'ID' => $team['id'],
            'Team' => $team['name'],
        ])->toArray();

        $this->table(['ID', 'Team'], $table);
    }
}
