<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Utility;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;

final class ConfigSetCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'config:set';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Write one CLI option');
    }

    protected function define(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Option name');
        $this->addArgument('value', InputArgument::OPTIONAL, 'Value, empty to unset it');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Always use JSON' => 'unolia config set format json',
            'Forget a value' => 'unolia config set browser',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $key = (string) $this->argumentString('key');
        $value = $this->argumentString('value');

        if ($this->dryRun()) {
            $this->out()->record([$key => $value, 'path' => $this->runtime()->paths()->settingsFile(), 'dry_run' => true]);

            return ExitCode::Ok;
        }

        $this->runtime()->settings()->set($key, $value);

        if ($this->structured()) {
            $this->out()->record([$key => $value, 'path' => $this->runtime()->paths()->settingsFile()]);

            return ExitCode::Ok;
        }

        $this->out()->info($value === null
            ? sprintf('Removed %s', $key)
            : sprintf('Set %s to %s', $key, $value));

        return ExitCode::Ok;
    }
}
