<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Website;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Requests\Websites\WebsiteEnv;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesTargets;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * The production .env of a website. Keys only unless the values are asked for out loud,
 * because these are live credentials and every read is audited.
 */
final class EnvCommand extends BaseCommand
{
    use ResolvesTargets;

    protected function canonical(): string
    {
        return 'website:env';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show the production environment keys of a website');
    }

    protected function define(): void
    {
        $this->addArgument('website', InputArgument::OPTIONAL, 'Website id or domain, the linked one by default');
        $this->addOption('values', null, InputOption::VALUE_NONE, 'Print the values too, which are live credentials');
        $this->addOption('keys-only', null, InputOption::VALUE_NONE, 'Print the key names, one per line');
    }

    public function examples(): array
    {
        return [
            'The keys in production' => 'unolia website env',
            'With values, on purpose' => 'unolia website env --values --yes',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $wantsValues = $this->optionBool('values');

        if ($wantsValues && ! $this->ask()->interactive() && ! $this->yes()) {
            throw CliError::confirmationRequired('print live credentials to this pipe');
        }

        if ($wantsValues && $this->ask()->interactive()
            && ! $this->ask()->confirm('Print the live credentials of this website?', false)) {
            throw CliError::usage('nothing was printed');
        }

        $data = $this->fetch(new WebsiteEnv(
            $this->websiteId(),
            $wantsValues ? [] : ['keys_only' => 1],
        ));

        $keys = [];

        foreach (is_array($data['keys'] ?? null) ? $data['keys'] : [] as $key) {
            if (is_string($key)) {
                $keys[] = $key;
            }
        }

        if ($wantsValues) {
            $content = is_string($data['content'] ?? null) ? $data['content'] : '';

            if ($this->structured()) {
                $this->out()->record(['keys' => $keys, 'content' => $content]);

                return ExitCode::Ok;
            }

            $this->out()->raw($content);

            return ExitCode::Ok;
        }

        if ($this->optionBool('keys-only') && ! $this->structured()) {
            $this->out()->raw(implode("\n", $keys)."\n");

            return ExitCode::Ok;
        }

        $this->out()->list(
            array_map(static fn (string $key): array => ['key' => $key], $keys),
            ['key' => 'Key'],
            null,
            'This website has no environment keys.',
        );

        return ExitCode::Ok;
    }
}
