<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class Dig extends Command
{
    protected $signature = 'dig {domain} {type=*} {--server?} {--return-target}';

    protected $description = 'Query DNS server for informations about a domain';

    public function handle()
    {

    }
}
