<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Team;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Core\ListTeams;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'team:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the teams this token can reach');
    }

    public function examples(): array
    {
        return [
            'Every team' => 'unolia team list',
            'Slugs only' => 'unolia teams --json slug',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListTeams($this->listQuery()));
        $current = $this->runtime()->context()->teamSlug();

        $this->out()->list(
            $rows,
            ['id' => 'Id', 'slug' => 'Slug', 'name' => 'Name', 'current' => ''],
            static fn (array $row): array => [
                'current' => $current !== null && in_array($current, [(string) ($row['slug'] ?? ''), (string) ($row['id'] ?? '')], true) ? '*' : '',
            ],
            'This token reaches no team.',
        );

        return ExitCode::Ok;
    }
}
