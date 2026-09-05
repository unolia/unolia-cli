<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Utility;

use Humbug\SelfUpdate\Updater;
use Phar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Throwable;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\VerifiedGithubStrategy;
use Unolia\Cli\Version;

/**
 * Update the CLI, whichever way it was installed.
 */
final class UpgradeCommand extends BaseCommand
{
    private const PACKAGE = 'unolia/unolia-cli';

    protected function canonical(): string
    {
        return 'upgrade';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Update the CLI to the latest version');
    }

    protected function define(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Only report whether a newer version exists');
        $this->addOption('rollback', null, InputOption::VALUE_NONE, 'Restore the previous phar');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Update' => 'unolia upgrade',
            'Only check' => 'unolia upgrade --check',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        return $this->runningAsPhar() ? $this->upgradePhar() : $this->explainComposer();
    }

    private function runningAsPhar(): bool
    {
        return class_exists(Phar::class) && Phar::running(false) !== '';
    }

    private function upgradePhar(): ExitCode
    {
        // No OpenSSL signature on the phar, so the release's own sha256 asset
        // is what says the download is the file the workflow built.
        $updater = new Updater(null, false);
        $strategy = new VerifiedGithubStrategy;
        $strategy->setPackageName(self::PACKAGE);
        $strategy->setPharName('unolia.phar');
        $strategy->setCurrentLocalVersion(Version::current());
        $updater->setStrategyObject($strategy);

        if ($this->optionBool('rollback')) {
            return $this->rollback($updater);
        }

        try {
            $hasUpdate = $updater->hasUpdate();
        } catch (Throwable $exception) {
            throw CliError::remoteFailure('could not check for updates: '.$exception->getMessage());
        }

        $latest = $updater->getNewVersion();

        if ($this->optionBool('check')) {
            $this->report(Version::current(), $latest, $hasUpdate);

            return $hasUpdate ? ExitCode::RemoteFailure : ExitCode::Ok;
        }

        if (! $hasUpdate) {
            $this->report(Version::current(), $latest, false);

            return ExitCode::Ok;
        }

        if ($this->dryRun()) {
            $this->report(Version::current(), $latest, true);

            return ExitCode::Ok;
        }

        try {
            $updater->update();
        } catch (Throwable $exception) {
            throw CliError::remoteFailure('the update failed: '.$exception->getMessage());
        }

        $this->report(Version::current(), $latest, false, true);

        return ExitCode::Ok;
    }

    private function rollback(Updater $updater): ExitCode
    {
        try {
            $rolled = $updater->rollback();
        } catch (Throwable $exception) {
            throw CliError::remoteFailure('the rollback failed: '.$exception->getMessage());
        }

        if (! $rolled) {
            throw CliError::remoteFailure('there is no previous version to roll back to');
        }

        $this->out()->info('Rolled back to the previous version');

        return ExitCode::Ok;
    }

    private function explainComposer(): ExitCode
    {
        $command = 'composer global update '.self::PACKAGE;

        if ($this->structured()) {
            $this->out()->record(['channel' => 'composer', 'command' => $command, 'version' => Version::current()]);

            return ExitCode::Ok;
        }

        if ($this->optionBool('check')) {
            $this->out()->note('Installed with Composer. Run: '.$command);

            return ExitCode::Ok;
        }

        if (! $this->yes()) {
            $this->out()->note('Installed with Composer. Run: '.$command);
            $this->out()->note('With cpx there is nothing to update: cpx always fetches the latest version.');

            return ExitCode::Ok;
        }

        if ($this->dryRun()) {
            $this->out()->note('Would run: '.$command);

            return ExitCode::Ok;
        }

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->out()->raw($buffer);
        });

        return $process->isSuccessful() ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    private function report(string $current, ?string $latest, bool $behind, bool $updated = false): void
    {
        if ($this->structured()) {
            $this->out()->record([
                'channel' => 'phar',
                'current' => $current,
                'latest' => $latest,
                'behind' => $behind,
                'updated' => $updated,
            ]);

            return;
        }

        if ($updated) {
            $this->out()->info(sprintf('Updated to %s', (string) $latest));

            return;
        }

        $this->out()->info(sprintf('Current %s, latest %s', $current, $latest ?? 'unknown'));
    }
}
