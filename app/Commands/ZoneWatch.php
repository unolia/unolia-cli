<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ZoneWatch extends Command
{
    protected $signature = 'zone:watch {record_id} {--interval=1} {--timeout=} {--once}';

    protected $description = 'Watch propagation of a record in a zone';

    public function handle()
    {
        //
    }
}
