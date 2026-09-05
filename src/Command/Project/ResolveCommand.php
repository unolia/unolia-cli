<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Project;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\Str;

/**
 * What this git remote maps to on Unolia. Reads only, writes nothing.
 */
final class ResolveCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'project:resolve';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show what this git remote maps to');
    }

    protected function define(): void
    {
        $this->addOption('remote', null, InputOption::VALUE_REQUIRED, 'A remote URL, the origin of this directory by default');
    }

    public function examples(): array
    {
        return [
            'What would init pick' => 'unolia project resolve',
            'For another remote' => 'unolia project resolve --remote git@github.com:acme/marketing.git',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $resolver = $this->runtime()->context();
        $match = $resolver->remoteMatch();

        if ($match === []) {
            throw CliError::notFound(
                'nothing on Unolia matches this directory',
                'Link it by hand with unolia init --website <id>.',
            );
        }

        if ($this->structured()) {
            $this->out()->record($match);

            return ExitCode::Ok;
        }

        $this->out()->record([
            'remote' => $match['remote'] ?? null,
            'repository' => Arr::get($match, 'parsed.full_name'),
            'confidence' => $match['confidence'] ?? null,
        ], [
            'remote' => 'Remote',
            'repository' => 'Repository',
            'confidence' => 'Confidence',
        ]);

        $websites = [];

        foreach ($resolver->remoteTeams() as $team) {
            foreach ((array) ($team['websites'] ?? []) as $website) {
                if (is_array($website)) {
                    $website['team'] = Arr::get($team, 'team.slug');
                    $websites[] = $website;
                }
            }
        }

        if ($websites !== []) {
            $this->out()->line('');
            $this->out()->list(
                $websites,
                ['id' => 'Id', 'domain' => 'Domain', 'environment' => 'Environment', 'branch' => 'Branch', 'team' => 'Team'],
                static fn (array $row): array => ['environment' => Str::scalar($row['environment'] ?? null)],
            );
        }

        return ExitCode::Ok;
    }
}
