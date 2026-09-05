<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Providers\ListProviders;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\RelativeTime;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'provider:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the connected providers');
    }

    protected function define(): void
    {
        $this->addOption('attention', null, InputOption::VALUE_NONE, 'Only providers that need attention');
    }

    public function examples(): array
    {
        return [
            'Every provider' => 'unolia provider list',
            'What needs attention' => 'unolia provider list --attention',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListProviders($this->listQuery()));

        if ($this->optionBool('attention')) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => ($row['needs_attention'] ?? false) === true));
        }

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'name' => 'Name',
                'provider' => 'Provider',
                'category' => 'Category',
                'state' => 'State',
                'synced_at' => 'Last synced',
                'needs_attention' => 'Attention',
            ],
            static fn (array $row): array => [
                'synced_at' => RelativeTime::ago(is_string($row['synced_at'] ?? null) ? $row['synced_at'] : null, null, 'never synced'),
                'needs_attention' => ($row['needs_attention'] ?? false) === true ? 'yes' : '',
            ],
            'No connected providers.',
        );

        return ExitCode::Ok;
    }
}
