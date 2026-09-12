<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Provider;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Providers\ShowProvider;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Browser;
use Unolia\Cli\Support\Str;

/**
 * A provider whose connection is broken is repaired on its page in the app:
 * new credentials, a fresh authorization. This opens that page.
 */
final class FixCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'provider:fix';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Open the page where a provider connection is repaired');
    }

    protected function define(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Provider id');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the URL instead of opening it');
    }

    public function examples(): array
    {
        return [
            'Repair a connection' => 'unolia provider fix 39',
            'Only the URL' => 'unolia provider fix 39 --print',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $id = (string) $this->argumentString('provider');
        $provider = $this->fetch(new ShowProvider($id));
        $url = $provider['fix_url'] ?? ($provider['url'] ?? null);

        if (! is_string($url) || $url === '') {
            throw CliError::notFound(sprintf('the API did not give a page for provider %s', $id));
        }

        $name = Str::scalar($provider['name'] ?? null, 'provider '.$id);
        $status = Str::scalar($provider['status'] ?? null, '');

        if ($this->structured()) {
            $this->out()->record(['id' => $provider['id'] ?? $id, 'name' => $name, 'status' => $status, 'url' => $url]);

            return ExitCode::Ok;
        }

        if ($status === 'ok') {
            $this->out()->note(sprintf('%s is connected; nothing to repair.', $name));
        }

        if ($this->optionBool('print') || ! $this->ask()->interactive()) {
            $this->out()->line($url);

            return ExitCode::Ok;
        }

        $browser = $this->runtime()->settings()->get('browser');

        if (! $this->runtime()->get(Browser::class)->open($url, is_string($browser) && $browser !== '' ? $browser : null)) {
            $this->out()->note('No browser opener on this machine.');
            $this->out()->line($url);

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Opened %s', $this->out()->link($url, $name)));
        $this->out()->note(sprintf('Repair the connection there, then unolia provider sync %s.', $id));

        return ExitCode::Ok;
    }
}
