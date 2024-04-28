<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class DomainAdd extends Command
{
    protected $signature = 'domain:add {type} {domain} {content} {--ttl=}';

    protected $description = 'Add a new record to a domain';

    public function handle()
    {
        //
    }
}
