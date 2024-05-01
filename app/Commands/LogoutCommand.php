<?php

namespace Unolia\UnoliaCLI\Commands;

use LaravelZero\Framework\Commands\Command;
use Unolia\UnoliaCLI\Helpers\Config;
use Unolia\UnoliaCLI\Helpers\Helpers;

class LogoutCommand extends Command
{
    protected $signature = 'logout';

    protected $description = 'Logout from unolia.com';

    public function handle()
    {
        $token = Helpers::getApiToken();

        if (empty($token)) {
            $this->error('You are not logged in.');

            return;
        }

        Config::set('api.token', null);

        $this->info('You are now logged out.');
    }
}
