<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Server;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Servers\ListServers;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

final class ListCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'server:list';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('List the managed servers of a project');
    }

    protected function define(): void
    {
        $this->addOption('all-projects', null, InputOption::VALUE_NONE, 'Every server of the team');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Filter by provider');
        $this->addOption('q', null, InputOption::VALUE_REQUIRED, 'Filter by name');
    }

    public function examples(): array
    {
        return [
            'Servers here' => 'unolia server list',
            'PHP versions' => 'unolia server list --json name,php_version',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $rows = $this->rows(new ListServers($this->listQuery([
            'project' => $this->optionBool('all-projects') ? null : $this->context(Need::None)->project,
            'provider' => $this->optionString('provider'),
            'q' => $this->optionString('q'),
        ])));

        $this->out()->list(
            $rows,
            [
                'id' => 'Id',
                'name' => 'Name',
                'provider' => 'Provider',
                'type' => 'Type',
                'public_ipv4' => 'IP',
                'php_version' => 'PHP',
                'database' => 'Database',
                'ubuntu_version' => 'Ubuntu',
                'status' => 'Status',
            ],
            static fn (array $row): array => [
                'provider' => Str::scalar(Arr::get($row, 'provider.slug')),
                'database' => self::database($row),
            ],
            'No managed servers here.',
        );

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function database(array $row): string
    {
        $engine = Arr::get($row, 'database.engine');
        $version = Arr::get($row, 'database.version');

        if (! is_string($engine)) {
            return Str::scalar(Arr::get($row, 'database.raw'));
        }

        return is_scalar($version) ? $engine.' '.$version : $engine;
    }
}
