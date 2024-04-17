<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ZoneUpdate extends Command
{
    protected $signature = 'zone:update {record_id} {content} {--ttl=}';

    protected $description = 'Update a record in a zone';

    public function handle()
    {
        //
    }
}
