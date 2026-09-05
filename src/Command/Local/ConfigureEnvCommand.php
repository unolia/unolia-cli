<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Local;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\WebsiteEnv;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Local\DotEnv;

/**
 * Add the keys production has to .env.example, with empty values. Values are never fetched.
 */
final class ConfigureEnvCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'configure:env';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Add the production environment keys to .env.example');
    }

    protected function define(): void
    {
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Which environment to read, production by default');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'File to write, .env.example by default');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Sync the example file' => 'unolia configure env',
            'See what is missing' => 'unolia configure env --dry-run',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $websiteId = $this->websiteFromEnvironment();
        $data = $this->fetch(new WebsiteEnv($websiteId, ['keys_only' => 1]));

        $keys = [];

        foreach (is_array($data['keys'] ?? null) ? $data['keys'] : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        $root = $this->runtime()->context()->config()->rootDir()
            ?? $this->runtime()->context()->git()->root()
            ?? $this->runtime()->cwd();

        $file = $this->optionString('output') ?? '.env.example';
        $path = str_starts_with($file, '/') ? $file : rtrim($root, '/').'/'.$file;

        $existing = array_keys((new DotEnv)->parse(is_file($path) ? (string) @file_get_contents($path) : ''));
        $missing = array_values(array_diff($keys, $existing));

        if ($this->dryRun() || $missing === []) {
            if ($this->structured()) {
                $this->out()->record(['path' => $path, 'added' => $missing, 'dry_run' => $this->dryRun()]);
            } elseif ($missing === []) {
                $this->out()->note(sprintf('%s already has every production key.', $file));
            } else {
                $this->out()->list(
                    array_map(static fn (string $key): array => ['key' => $key], $missing),
                    ['key' => 'Would add'],
                );
            }

            return ExitCode::Ok;
        }

        $this->append($path, $missing);

        if ($this->structured()) {
            $this->out()->record(['path' => $path, 'added' => $missing, 'dry_run' => false]);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Added %d key%s to %s', count($missing), count($missing) === 1 ? '' : 's', $file));
        $this->out()->note(implode(', ', $missing));

        return ExitCode::Ok;
    }

    private function websiteFromEnvironment(): int
    {
        $from = $this->optionString('from');

        if ($from === null) {
            return $this->websiteId();
        }

        $environments = $this->runtime()->context()->environments();

        if (! isset($environments[$from])) {
            throw CliError::notFound(
                sprintf('there is no %s environment here', $from),
                $environments === []
                    ? 'Run unolia init to write the environments map.'
                    : 'Known environments: '.implode(', ', array_keys($environments)),
                ['candidates' => array_keys($environments)],
            );
        }

        return $environments[$from];
    }

    /**
     * @param  list<string>  $keys
     */
    private function append(string $path, array $keys): void
    {
        $contents = is_file($path) ? (string) @file_get_contents($path) : '';
        $prefix = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";
        $block = $prefix.implode("=\n", $keys)."=\n";

        if (@file_put_contents($path, $contents.$block) === false) {
            throw CliError::usage(sprintf('could not write %s', $path));
        }
    }
}
