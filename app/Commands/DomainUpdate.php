<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class DomainUpdate extends Command
{
    protected $signature = 'domain:update {record_id} {content} {--ttl=}';

    protected $description = 'Update a record in a domain';

    public function handle()
    {
        //
    }
}
