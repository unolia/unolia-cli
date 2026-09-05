<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Local;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Projects\ProjectVersions;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;

/**
 * Every production version of a project, one row per package, one column per subject.
 */
final class CompareVersionsCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'compare:versions';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Compare the versions across a project');
    }

    protected function define(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Only this package');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'composer, npm or runtime');
    }

    public function examples(): array
    {
        return [
            'Every version' => 'unolia compare versions',
            'One package' => 'unolia compare versions --name laravel/framework',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $versions = $this->collection(new ProjectVersions($this->projectId(), array_filter([
            'name' => $this->optionString('name'),
            'type' => $this->optionString('type'),
        ], static fn (mixed $value): bool => $value !== null)));

        if ($this->structured()) {
            $this->out()->list($versions, ['name' => 'Package', 'type' => 'Type', 'installed_version' => 'Version']);

            return ExitCode::Ok;
        }

        $subjects = [];
        $rows = [];

        foreach ($versions as $version) {
            $name = $version['name'] ?? null;
            $subject = Arr::get($version, 'subject.name');

            if (! is_string($name) || ! is_string($subject)) {
                continue;
            }

            $subjects[$subject] = true;
            $rows[$name]['name'] = $name;
            $rows[$name]['type'] = $version['type'] ?? '';
            $rows[$name][$subject] = $version['installed_version'] ?? '-';
        }

        $columns = ['name' => 'Package', 'type' => 'Type'];

        foreach (array_keys($subjects) as $subject) {
            $columns[$subject] = $subject;
        }

        $table = [];

        foreach ($rows as $row) {
            foreach (array_keys($subjects) as $subject) {
                $row[$subject] ??= '-';
            }

            $known = array_filter(array_intersect_key($row, $subjects), static fn (mixed $value): bool => $value !== '-');
            $row['disagrees'] = count(array_unique($known)) > 1;
            $table[] = $row;
        }

        usort($table, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        $this->out()->list(
            $table,
            $columns,
            static fn (array $row): array => ['name' => ($row['disagrees'] === true ? '! ' : '  ').(string) $row['name']],
            'This project reports no versions yet.',
        );

        return ExitCode::Ok;
    }
}
