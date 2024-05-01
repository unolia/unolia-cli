<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Config;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentAuthenticated;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentToken;

use function Laravel\Prompts\password;

class LoginCommand extends Command
{
    protected $signature = 'login {--token= : Unolia API token}';

    protected $description = 'Authenticate with the unolia.com';

    public function handle()
    {
        $token = Helpers::getApiToken();

        if (! empty($token)) {
            $this->line('You are already logged in.');

            return;
        }

        $token = password(
            label: 'API Token',
            placeholder: '',
            hint: 'You can find your API token in your unolia.com account settings',
        );

        $connector = Helpers::getApiConnector($token);
        try {
            $response = $connector->send(new CurrentToken());
            $response->throw();
        } catch (\Exception $e) {
            $this->error('Failed to authenticate with the API: '.$e->getMessage());

            return;
        }

        $current_token = $response->json('data');

        $response = $connector->send(new CurrentAuthenticated());

        if ($response->failed()) {
            $this->error('Failed to fetch user details: '.($response->json('message') ?: 'Unknown error'));

            return;
        }

        $user = $response->json('data');

        Config::set('api.token', $token);

        $this->info('You are now logged in with '.$current_token['tokenable_type'].': '.$user['name'].'.');
    }
}
