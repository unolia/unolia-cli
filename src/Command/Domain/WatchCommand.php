<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Watch\RecordTarget;

final class WatchCommand extends BaseCommand
{
    use Watches;

    protected function canonical(): string
    {
        return 'domain:watch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Watch a record until it propagates');
    }

    protected function define(): void
    {
        $this->addArgument('record', InputArgument::REQUIRED, 'Record id');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Check once and stop');
        $this->addWatchOptions('30s', 2);
    }

    public function examples(): array
    {
        return [
            'Watch a record' => 'unolia domain watch 88231',
            'Check it once' => 'unolia domain watch 88231 --once',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('record');

        if (! ctype_digit($id)) {
            throw CliError::usage('the record id must be a number');
        }

        $result = $this->follow(new RecordTarget($this->api(), (int) $id, $this->optionBool('once')));

        if ($this->structured()) {
            $this->out()->record($result->state->data);
        }

        return $result->exitCode;
    }
}
