<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Utility;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;

final class ConfigListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'config:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show every CLI option');
    }

    public function examples(): array
    {
        return [
            'Every option' => 'unolia config list',
            'As an object' => 'unolia config list --json',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $values = $this->runtime()->settings()->effective();

        if ($this->structured()) {
            $this->out()->record($values);

            return ExitCode::Ok;
        }

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = ['key' => $key, 'value' => Str::scalar($value, '')];
        }

        $this->out()->list($rows, ['key' => 'Key', 'value' => 'Value']);

        return ExitCode::Ok;
    }
}
