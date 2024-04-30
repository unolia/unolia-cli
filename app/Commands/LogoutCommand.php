<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class LogoutCommand extends Command
{
    protected $signature = 'logout';

    protected $description = 'Logout from unolia.com';

    public function handle()
    {
        $this->line('Not implemented yet. Use UNOLIA_API_TOKEN environment variable.');
    }
}
