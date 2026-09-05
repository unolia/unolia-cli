<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Team;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Core\ListTeams;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\ProjectConfig;

/**
 * Choose the team every later command works in.
 */
final class SwitchCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'team:switch';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Choose the default team');
    }

    protected function define(): void
    {
        $this->addArgument('team', InputArgument::OPTIONAL, 'Team slug');
        $this->addOption('local', null, InputOption::VALUE_NONE, 'Write it into .unolia/config.json instead');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Everywhere' => 'unolia team switch acme',
            'This repository only' => 'unolia team switch acme --local',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $teams = $this->teams();
        $slug = $this->argumentString('team') ?? $this->ask()->select('Which team?', $teams, '--team');

        if (! isset($teams[$slug])) {
            throw CliError::notFound(
                sprintf('no team called %s', $slug),
                'Run unolia team list to see the ones you can reach.',
                ['candidates' => $teams],
            );
        }

        if ($this->dryRun()) {
            $this->out()->record(['team' => $slug, 'scope' => $this->optionBool('local') ? 'directory' : 'global']);

            return ExitCode::Ok;
        }

        if ($this->optionBool('local')) {
            $root = $this->runtime()->context()->config()->rootDir() ?? $this->runtime()->context()->git()->root();

            if ($root === null) {
                throw CliError::usage('there is no repository here', 'Run unolia team switch without --local.');
            }

            $data = $this->runtime()->context()->config()->data;
            $data['team'] = $slug;
            $path = ProjectConfig::write($root, $data);

            $this->report($slug, $path);

            return ExitCode::Ok;
        }

        $this->runtime()->settings()->set('default_team', $slug);

        $this->report($slug, $this->runtime()->paths()->settingsFile());

        return ExitCode::Ok;
    }

    /**
     * @return array<string, string>
     */
    private function teams(): array
    {
        $teams = [];

        foreach ($this->collection(new ListTeams(['per_page' => 100])) as $team) {
            $slug = $team['slug'] ?? $team['id'] ?? null;

            if ($slug !== null && is_scalar($slug)) {
                $teams[(string) $slug] = (string) ($team['name'] ?? $slug);
            }
        }

        return $teams;
    }

    private function report(string $slug, string $path): void
    {
        if ($this->structured()) {
            $this->out()->record(['team' => $slug, 'path' => $path]);

            return;
        }

        $this->out()->info(sprintf('Now working in team %s', $slug));
    }
}
