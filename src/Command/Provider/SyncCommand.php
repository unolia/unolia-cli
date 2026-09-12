<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Providers\ShowProvider;
use Unolia\Cli\Api\Requests\Providers\SyncProvider;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\Watches;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Str;
use Unolia\Cli\Watch\ProviderSyncTarget;

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
        $this->addFollowOptions('10m', 3);
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Sync a provider and watch it land' => 'unolia provider sync 14',
            'Queue it and come back later' => 'unolia provider sync 14 --no-progress',
            'Block in a script' => 'unolia provider sync 14 --wait',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('provider');

        if ($this->dryRun()) {
            $preview = $this->fetch(new SyncProvider($id, ['dry_run' => true]));

            if ($this->structured()) {
                $this->out()->record($preview);
            } else {
                $this->out()->record($preview, ['dry_run' => 'Dry run', 'job' => 'Job', 'would_dispatch' => 'Would sync', 'status' => 'Connection', 'url' => 'Page']);

                if (($preview['would_dispatch'] ?? true) === false) {
                    $this->out()->note(sprintf('The connection is %s. unolia provider fix %s opens the page that repairs it.', str_replace('_', ' ', Str::scalar($preview['status'] ?? null, 'broken')), $id));
                }
            }

            return ExitCode::Ok;
        }

        $before = $this->fetch(new ShowProvider($id));
        $syncedAt = $before['synced_at'] ?? null;

        $provider = $this->fetch(new SyncProvider($id, ['dry_run' => false]));

        $name = Str::scalar($provider['name'] ?? null, 'provider '.$id);

        return $this->followByDefault(
            new ProviderSyncTarget($this->api(), $id, $syncedAt),
            sprintf('Syncing %s', $name),
            $provider,
            sprintf('Queued a sync of %s · unolia provider view %s', $name, $id),
            sprintf('unolia provider view %s shows the sync state.', $id),
        );
    }
}
