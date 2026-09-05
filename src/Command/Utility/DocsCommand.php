<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Utility;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Application;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Groups;
use Unolia\Cli\Console\HelpRenderer;

/**
 * One Markdown file per command, from the same metadata the help renderer uses, so the
 * website's docs can never drift from `--help`.
 */
final class DocsCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'docs:generate';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Write the command reference into docs/cli');
        $this->setHidden();
    }

    protected function define(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Directory to write into', 'docs/cli');
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Fail when the files on disk differ');
    }

    public function learnMore(): ?string
    {
        return null;
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $application = $this->getApplication();

        if (! $application instanceof Application) {
            return ExitCode::RemoteFailure;
        }

        $directory = rtrim($this->optionString('output') ?? 'docs/cli', '/');
        $renderer = new HelpRenderer($this->runtime());
        $written = [];
        $stale = [];

        foreach ($this->commands($application) as $name => $command) {
            $file = $directory.'/'.str_replace(':', '-', $name).'.md';
            $contents = $this->markdown($command, $renderer);

            if ($this->optionBool('check')) {
                if (! is_file($file) || (string) @file_get_contents($file) !== $contents) {
                    $stale[] = $file;
                }

                continue;
            }

            $this->runtime()->paths()->writeAtomic($file, $contents, 0644);
            $written[] = $file;
        }

        if ($this->optionBool('check')) {
            if ($stale !== []) {
                throw CliError::remoteFailure(
                    sprintf('%d documentation file%s out of date', count($stale), count($stale) === 1 ? ' is' : 's are'),
                    'Run php bin/unolia docs:generate and commit the result.',
                    ['files' => $stale],
                );
            }

            $this->out()->info('The documentation matches the commands');

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Wrote %d files into %s', count($written), $directory));

        return ExitCode::Ok;
    }

    /**
     * @return array<string, Command>
     */
    private function commands(Application $application): array
    {
        $commands = [];

        foreach ($application->all() as $name => $command) {
            if ($command->isHidden() || $command->getName() !== $name || $name === '_complete') {
                continue;
            }

            $commands[$name] = $command instanceof DumpCompletionCommand
                ? $this->canonicalCompletionCommand()
                : $command;
        }

        ksort($commands);

        return $commands;
    }

    /**
     * Symfony's completion command writes its help at construction from the
     * machine it runs on: the shell in `$SHELL` and the binary's real path. The
     * reference is committed, so it is rendered against one fixed environment
     * and reads the same on every machine and in CI.
     */
    private function canonicalCompletionCommand(): DumpCompletionCommand
    {
        $previous = [
            'SHELL' => $_SERVER['SHELL'] ?? null,
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
        ];

        $_SERVER['SHELL'] = '/bin/zsh';
        $_SERVER['PHP_SELF'] = 'unolia';

        try {
            $command = new DumpCompletionCommand;
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }

        $command->setApplication($this->getApplication());

        return $command;
    }

    private function markdown(Command $command, HelpRenderer $renderer): string
    {
        $display = Groups::display((string) $command->getName());
        $description = $command->getDescription();
        $help = $renderer->command($command);

        // The page opens with the description, so the help block starts at USAGE.
        if (str_starts_with($help, $description."\n\n")) {
            $help = substr($help, strlen($description) + 2);
        }

        return sprintf(
            "# unolia %s\n\n%s\n\n```\n%s\n```\n",
            $display,
            $description,
            self::withoutLocalPath($help),
        );
    }

    /**
     * Symfony's completion help names the binary by its absolute path on the
     * machine that generated it. The reference is committed and read on other
     * machines, so the path becomes the command people actually type.
     */
    private static function withoutLocalPath(string $help): string
    {
        $paths = [];

        foreach ([$_SERVER['PHP_SELF'] ?? null, $_SERVER['argv'][0] ?? null] as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $paths[] = $path;

            $real = @realpath($path);

            if (is_string($real)) {
                $paths[] = $real;
            }
        }

        usort($paths, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace(array_unique($paths), 'unolia', $help);
    }
}
