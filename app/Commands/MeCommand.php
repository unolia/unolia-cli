<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentAuthenticated;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentToken;

class MeCommand extends Command
{
    protected $signature = 'me';

    protected $description = 'Show the current user details';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

        $response = $connector->send(new CurrentToken());

        if ($response->failed()) {
            $this->error('Failed to fetch token info: '.($response->json('message') ?: 'Unknown error'));

            return;
        }

        $token = $response->json('data');

        $response = $connector->send(new CurrentAuthenticated());

        if ($response->failed()) {
            $this->error('Failed to fetch user details: '.($response->json('message') ?: 'Unknown error'));

            return;
        }

        $user = $response->json('data');

        if ($token['tokenable_type'] == 'user') {
            $this->info('You are using a user token');
            $this->line('Id: '.$user['id']);
            $this->line('Name: '.$user['name']);
            $this->line('Email: '.$user['email']);
        } elseif ($token['tokenable_type'] == 'team') {
            $this->info('You are using a team token');
            $this->line('Id: '.$user['id']);
            $this->line('Name: '.$user['name']);
        }
    }
}
