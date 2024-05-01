<?php

namespace App\Commands;

use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelZero\Framework\Commands\Command;
use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Dns\Query\UdpTransportExecutor;

use function Laravel\Prompts\select;

class DigCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'dig {domain} {type} {--server=1.1.1.1}';

    protected $description = 'Query DNS server for informations about a domain';

    public function handle()
    {
        $domain = $this->argument('domain');
        $type_string = strtoupper($this->argument('type'));
        $server = $this->option('server');

        // TODO : validate domain and server

        $type = match ($type_string) {
            'A' => Message::TYPE_A,
            'AAAA' => Message::TYPE_AAAA,
            'CNAME' => Message::TYPE_CNAME,
            // 'DNAME' => Message::TYPE_DNAME, // Not supported for now
            'NS' => Message::TYPE_NS,
            'MX' => Message::TYPE_MX,
            'PTR' => Message::TYPE_PTR,
            'SOA' => Message::TYPE_SOA,
            'SRV' => Message::TYPE_SRV,
            'SSHFP' => Message::TYPE_SSHFP,
            'TXT' => Message::TYPE_TXT,
            default => throw new \InvalidArgumentException("Invalid record type: $type_string"),
        };

        $table = [];
        $executor = new UdpTransportExecutor($server.':53');

        $executor
            ->query(
                new Query($domain, $type, Message::CLASS_IN)
            )
            ->then(function (Message $message) use (&$table, $type_string) {
                foreach ($message->answers as $answer) {
                    $table[] = [
                        'Name' => $answer->name,
                        'Type' => $type_string,
                        'TTL' => $answer->ttl,
                        'Value' => is_array($answer->data) ? implode(' ', $answer->data) : $answer->data,
                    ];
                }

                $this->table(['Name', 'Type', 'TTL', 'Value'], $table);
            }, fn (\Throwable $e) => $this->error($e->getMessage()));

    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'domain' => [
                'Enter the domain name',
                'example.com',
            ],
            'type' => fn () => select(
                'Select the record type',
                ['A', 'AAAA', 'CNAME', 'NS', 'MX', 'PTR', 'SOA', 'SRV', 'SSHFP', 'TXT'],
                'A'
            ),
        ];
    }
}
