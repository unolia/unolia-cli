<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Utility;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;

final class ConfigGetCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'config:get';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Read one CLI option');
    }

    protected function define(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Option name');
    }

    public function examples(): array
    {
        return [
            'The default team' => 'unolia config get default_team',
            'The host every request goes to' => 'unolia config get host',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $key = (string) $this->argumentString('key');
        $value = $this->runtime()->settings()->get($key);

        if ($this->structured()) {
            $this->out()->record([$key => $value]);

            return ExitCode::Ok;
        }

        $this->out()->raw(Str::scalar($value, '').PHP_EOL);

        return ExitCode::Ok;
    }
}
