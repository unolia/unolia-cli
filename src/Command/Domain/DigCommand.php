<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use React\Dns\Model\Message;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Dns;

/**
 * The only command that talks to something other than the Unolia API: a DNS resolver.
 */
final class DigCommand extends BaseCommand
{
    private const TYPES = [
        'A' => Message::TYPE_A,
        'AAAA' => Message::TYPE_AAAA,
        'CNAME' => Message::TYPE_CNAME,
        'NS' => Message::TYPE_NS,
        'MX' => Message::TYPE_MX,
        'PTR' => Message::TYPE_PTR,
        'SOA' => Message::TYPE_SOA,
        'SRV' => Message::TYPE_SRV,
        'SSHFP' => Message::TYPE_SSHFP,
        'TXT' => Message::TYPE_TXT,
    ];

    protected function canonical(): string
    {
        return 'domain:dig';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Query a DNS server about a domain');
    }

    protected function define(): void
    {
        $this->addArgument('domain', InputArgument::OPTIONAL, 'The name to look up');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type, A by default');
        $this->addOption('server', null, InputOption::VALUE_REQUIRED, 'Resolver to ask', '1.1.1.1');
    }

    public function examples(): array
    {
        return [
            'Look up an address' => 'unolia dig acme.com',
            'Ask another resolver' => 'unolia dig acme.com TXT --server 8.8.8.8',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $domain = $this->argumentString('domain')
            ?? $this->ask()->text('Domain name', '<domain>', 'example.com');

        $typeName = strtoupper($this->argumentString('type')
            ?? ($this->ask()->interactive()
                ? $this->ask()->select('Record type', array_combine(array_keys(self::TYPES), array_keys(self::TYPES)), '<type>', 'A')
                : 'A'));

        if (! isset(self::TYPES[$typeName])) {
            throw CliError::usage(
                sprintf('%s is not a record type this command knows', $typeName),
                'Known types: '.implode(', ', array_keys(self::TYPES)),
                ['candidates' => array_keys(self::TYPES)],
            );
        }

        $rows = $this->runtime()->get(Dns::class)->query(
            $domain,
            $typeName,
            self::TYPES[$typeName],
            $this->optionString('server') ?? '1.1.1.1',
        );

        $this->out()->list(
            $rows,
            ['name' => 'Name', 'type' => 'Type', 'ttl' => 'TTL', 'value' => 'Value'],
            null,
            sprintf('No %s record for %s.', $typeName, $domain),
        );

        return $rows === [] ? ExitCode::NotFound : ExitCode::Ok;
    }
}
