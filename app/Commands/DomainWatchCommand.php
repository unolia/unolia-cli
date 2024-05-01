<?php

namespace Unolia\UnoliaCLI\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;
use Saloon\Exceptions\Request\RequestException;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\DomainsRecordsShow;

class DomainWatchCommand extends Command
{
    protected $signature = 'domain:watch {record_id} {--interval=2} {--timeout=30} {--once}';

    protected $description = 'Watch propagation of a record in a domain';

    public function handle()
    {
        $connector = Helpers::getApiConnector();

        try {
            $response = $connector->send(new DomainsRecordsShow(
                record: $this->argument('record_id'),
            ));
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $interval = intval($this->option('interval')) <= 0 ? 2 : intval($this->option('interval'));

        $record = $response->json('data');

        $start = time();
        $this->line(''); // Blank line
        $this->line('  Watching the following record: '.implode(' | ', [
            $record['name'],
            $record['type'],
            $record['value'],
        ]));

        $this->line(''); // Blank line

        $this->line('  Checking propagation...');

        while (true) {
            try {
                $response = $connector->send(new DomainsRecordsShow(
                    record: $this->argument('record_id'),
                ));
            } catch (RequestException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $record = $response->json('data');

            if ($record['state'] === 'verified') {
                $this->info('  Record is active');
                break;
            }

            $this->line('  Record is not active yet');

            if ($this->option('once')) {
                break;
            }

            if (time() - $start >= $this->option('timeout')) {
                $this->error('  Timeout reached');
                break;
            }

            sleep($interval);
        }

        $this->line(''); // Blank line

        return self::SUCCESS;
    }
}
