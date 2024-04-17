<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ZoneAdd extends Command
{
    protected $signature = 'zone:add {type} {domain} {content} {--ttl=}';

    protected $description = 'Add a new record to a zone';

    public function handle()
    {
        //
    }
}
