<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class Logout extends Command
{
    protected $signature = 'logout';

    protected $description = 'Logout from unolia.com';

    public function handle()
    {
        //
    }
}
