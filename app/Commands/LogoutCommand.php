<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Config;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\Logout;

class LogoutCommand extends Command
{
    protected $signature = 'logout {--force : Force the operation when the actual token is already deleted from your account}';

    protected $description = 'Logout from unolia.com';

    public function handle()
    {
        $token = Helpers::getApiToken();

        if (empty($token)) {
            $this->error('You are not logged in.');

            return;
        }

        $connector = Helpers::getAuthConnector();
        $logout = $connector->send(new Logout);

        if ($logout->failed() && ! $this->option('force')) {
            $this->error('Logout failed: '.$logout->json('message'));

            return;
        }

        Config::set('api.token', null);

        $this->info('You are now logged out.');
    }
}
