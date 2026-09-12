<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Dns\Query\Query;
use React\Dns\Query\TimeoutExecutor;
use React\Dns\Query\UdpTransportExecutor;
use React\EventLoop\Loop;
use Throwable;
use Unolia\Cli\Console\CliError;

/**
 * A DNS query, answered synchronously.
 *
 * ReactPHP resolves a query on its event loop, so a caller that reads the
 * promise's result without running the loop reads nothing. This runs the loop
 * until the answer or the timeout arrives, which is the whole reason it exists.
 */
class Dns
{
    public function __construct(private readonly float $timeout = 5.0) {}

    /**
     * @return list<array{name: string, type: string, ttl: int, value: string}>
     */
    public function query(string $domain, string $typeName, int $type, string $server): array
    {
        $rows = [];
        $failure = null;

        $executor = new TimeoutExecutor(new UdpTransportExecutor(self::address($server)), $this->timeout);

        $executor
            ->query(new Query($domain, $type, Message::CLASS_IN))
            ->then(
                function (Message $message) use (&$rows, $typeName): void {
                    foreach ($message->answers as $answer) {
                        $rows[] = [
                            'name' => $answer->name,
                            'type' => $typeName,
                            'ttl' => $answer->ttl,
                            'value' => self::value($answer),
                        ];
                    }
                },
                function (Throwable $exception) use (&$failure): void {
                    $failure = $exception->getMessage();
                },
            );

        Loop::run();

        if ($failure !== null) {
            throw CliError::remoteFailure(
                sprintf('%s did not answer: %s', $server, $failure),
                'Try another resolver with --server.',
            );
        }

        return $rows;
    }

    /** The resolver as host:port, with an IPv6 address in brackets. */
    private static function address(string $server): string
    {
        $server = trim($server);

        if (str_contains($server, ':') && ! str_starts_with($server, '[')) {
            return '['.$server.']:53';
        }

        if (str_contains($server, ']:') || preg_match('/:\d+$/', $server) === 1) {
            return $server;
        }

        // A nameserver given by name (ns1.example.com) has to become an
        // address first: the transport speaks UDP to an IP, not to a name.
        if (filter_var($server, FILTER_VALIDATE_IP) === false) {
            $resolved = gethostbyname($server);

            if ($resolved === $server) {
                throw CliError::remoteFailure(sprintf('%s could not be resolved to an address', $server), 'Pass the resolver as an IP with --server.');
            }

            $server = $resolved;
        }

        return $server.':53';
    }

    private static function value(Record $answer): string
    {
        $data = $answer->data;

        if (is_array($data)) {
            return implode(' ', array_map(strval(...), $data));
        }

        return is_scalar($data) ? (string) $data : '';
    }
}
