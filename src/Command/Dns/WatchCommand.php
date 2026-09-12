<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Dns;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\RecordTarget;

final class WatchCommand extends BaseCommand
{
    use ResolvesZones;
    use Watches;

    protected function canonical(): string
    {
        return 'dns:watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Watch a DNS record until it propagates');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id, or its name');
        $this->addArgument('type', InputArgument::OPTIONAL, 'Record type, when the name alone is not enough');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'The zone, the project\'s one by default');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Check once and stop');
        $this->addWatchOptions('30s', 2);
    }

    public function examples(): array
    {
        return [
            'Watch a record' => 'unolia dns watch www A',
            'Check it once' => 'unolia dns watch 88231 --once',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $record = $this->record((string) $this->argumentString('record'), $this->argumentString('type'));
        $result = $this->follow(new RecordTarget($this->api(), (int) ($record['id'] ?? 0), $this->optionBool('once')));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
