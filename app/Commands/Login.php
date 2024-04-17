<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class Login extends Command
{
    protected $signature = 'login {--token= : Forge API token}';

    protected $description = 'Authenticate with the unolia.com';

    public function handle()
    {
        //
    }
}
