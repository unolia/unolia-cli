<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ZoneRemove extends Command
{
    protected $signature = 'zone:remove {record_id}';

    protected $description = 'Remove a record from a zone';

    public function handle()
    {
        //
    }
}
