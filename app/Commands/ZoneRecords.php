<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ZoneRecords extends Command
{
    protected $signature = 'zone:records {domain}';

    protected $description = 'List all records for a domain';

    public function handle()
    {
        //
    }
}
