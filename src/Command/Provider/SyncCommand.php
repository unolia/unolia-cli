<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Providers\ShowProvider;
use Unolia\Cli\Api\Requests\Providers\SyncProvider;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * Queue a sync of one provider, and optionally wait for it to land.
 */
final class SyncCommand extends BaseCommand
{
    use Watches;

    protected function canonical(): string
    {
        return 'provider:sync';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Synchronize a connected provider');
    }

    protected function define(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Provider id');
        $this->addOption('wait', null, InputOption::VALUE_NONE, 'Wait until the sync lands');
        $this->addWatchOptions('10m');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Sync a provider' => 'unolia provider sync 14',
            'Sync and wait' => 'unolia provider sync 14 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('provider');

        if ($this->dryRun()) {
            $this->out()->record($this->fetch(new SyncProvider($id, ['dry_run' => true])));

            return ExitCode::Ok;
        }

        $before = $this->fetch(new ShowProvider($id));
        $syncedAt = $before['synced_at'] ?? null;

        $provider = $this->fetch(new SyncProvider($id, ['dry_run' => false]));

        if (! $this->optionBool('wait')) {
            if ($this->structured()) {
                $this->out()->record($provider);

                return ExitCode::Ok;
            }

            $this->out()->info(sprintf('Queued a sync of %s', (string) ($provider['name'] ?? $id)));

            return ExitCode::Ok;
        }

        $timeout = $this->duration('timeout', 600);
        $started = time();

        while (true) {
            $current = $this->fetch(new ShowProvider($id));

            if (($current['synced_at'] ?? null) !== $syncedAt) {
                if ($this->structured()) {
                    $this->out()->record($current);
                } else {
                    $this->out()->info(sprintf('Synced %s', (string) ($current['name'] ?? $id)));
                }

                return ($current['has_sync_error'] ?? false) === true ? ExitCode::RemoteFailure : ExitCode::Ok;
            }

            if (time() - $started >= $timeout) {
                throw CliError::timeout(
                    'the sync is still running',
                    'Check it later with unolia provider view '.$id,
                );
            }

            $this->runtime()->poller()->sleep($this->duration('interval', 3));
        }
    }
}
