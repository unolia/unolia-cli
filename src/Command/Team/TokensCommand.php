<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Team;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Support\Browser;

/**
 * Team tokens are minted in the browser, never here.
 */
final class TokensCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'team:tokens';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Open the team tokens page');
    }

    protected function define(): void
    {
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the URL instead of opening it');
    }

    public function examples(): array
    {
        return [
            'Manage team tokens' => 'unolia team tokens',
            'Just the URL' => 'unolia team tokens --print',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $team = $this->context(Need::Team)->team;
        $url = sprintf('https://%s/%s/team/api-tokens', $this->runtime()->host(), (string) $team);

        if ($this->optionBool('print') || ! $this->ask()->interactive()) {
            $this->printUrl($url);

            return ExitCode::Ok;
        }

        $this->runtime()->get(Browser::class)->open($url);
        $this->out()->info('Opened '.$url);

        return ExitCode::Ok;
    }
}
