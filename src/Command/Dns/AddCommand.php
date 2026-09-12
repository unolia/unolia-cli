<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Domains\CreateRecord;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\PropagatesRecords;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;

/**
 * Add a record, in zone file order: name, type, value. Adding is reversible,
 * so nothing is asked beyond the values that are missing.
 */
final class AddCommand extends BaseCommand
{
    use PropagatesRecords;
    use ResolvesZones;

    protected function canonical(): string
    {
        return 'dns:add';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Add a DNS record');
    }

    protected function define(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Record name, relative to the zone: www, @ for the zone itself, or a full hostname');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type');
        $this->addArgument('value', InputArgument::OPTIONAL, 'Record value');
        $this->addArgument('legacy', InputArgument::OPTIONAL, 'Unused; lets the old "domain add <zone> <name> <type> <value>" keep working');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone, the project\'s one by default');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Time to live in seconds');
        $this->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority, for MX and SRV');
        $this->addPropagationOptions();
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Point a subdomain' => 'unolia dns add www A 203.0.113.10',
            'A TXT record on the zone itself' => 'unolia dns add @ TXT "v=spf1 include:_spf.example.com ~all"',
            'A mail server' => 'unolia dns add @ MX mail.acme.com --priority 10',
            'In another zone' => 'unolia dns add www A 203.0.113.10 --zone acme.dev',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        [$explicitZone, $name, $type, $value] = $this->positionals();

        $zone = $this->zone($explicitZone, $name);
        $name ??= $this->ask()->text('Record name', '<name>', 'www', '', 'Relative to '.$zone.', @ for the zone itself');
        $type = self::checkType($type ?? $this->ask()->select('Record type', array_combine(self::RECORD_TYPES, self::RECORD_TYPES), '<type>', 'A'));
        $value ??= $this->ask()->text('Value', '<value>', '', '', self::hintFor($type));

        $body = Arr::filled([
            'name' => self::fullName($name, $zone),
            'type' => $type,
            'value' => $value,
            'ttl' => $this->optionInt('ttl'),
            'priority' => $this->optionInt('priority'),
        ]);

        if ($this->dryRun()) {
            $this->out()->record(['zone' => $zone] + $body);

            return ExitCode::Ok;
        }

        return $this->propagate($this->fetch(new CreateRecord($zone, $body)), $zone, 'Added');
    }

    /**
     * The three arguments, or the old four where the zone came first.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function positionals(): array
    {
        $legacy = $this->argumentString('legacy');

        if ($legacy !== null) {
            return [$this->argumentString('name'), $this->argumentString('type'), $this->argumentString('value'), $legacy];
        }

        return [null, $this->argumentString('name'), $this->argumentString('type'), $this->argumentString('value')];
    }
}
