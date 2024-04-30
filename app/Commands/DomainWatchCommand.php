<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class DomainWatchCommand extends Command
{
    protected $signature = 'domain:watch {record_id} {--interval=1} {--timeout=} {--once}';

    protected $description = 'Watch propagation of a record in a domain';

    public function handle()
    {
        //
    }
}
