<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Domains\ListDomains;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'domain:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the domains this token can reach');
    }

    public function examples(): array
    {
        return [
            'Every domain' => 'unolia domain list',
            'Names only' => 'unolia domain list --json domain',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListDomains($this->listQuery()));
        $team = $this->optionString('team');

        if ($team !== null) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => in_array($team, [
                (string) (Arr::get($row, 'team.slug') ?? ''),
                (string) (Arr::get($row, 'team.id') ?? ''),
            ], true)));
        }

        $this->out()->list(
            $rows,
            ['domain' => 'Domain', 'team' => 'Team', 'synced_at' => 'Last synced'],
            static fn (array $row): array => [
                'team' => (string) (Arr::get($row, 'team.name') ?? Arr::get($row, 'team.slug') ?? '-'),
                'synced_at' => RelativeTime::ago(is_string($row['synced_at'] ?? null) ? $row['synced_at'] : null, null, 'never synced'),
            ],
            'No domains yet.',
        );

        return ExitCode::Ok;
    }
}
