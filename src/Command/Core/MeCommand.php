<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Symfony\Component\Console\Input\InputInterface;
use Unolia\Cli\Api\Requests\Core\CurrentAuthenticated;
use Unolia\Cli\Api\Requests\Core\CurrentToken;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\ExitCode;

/**
 * Who the current token belongs to.
 */
final class MeCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'me';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show the current user or team token');
    }

    public function examples(): array
    {
        return [
            'Who am I' => 'unolia me',
            'In a script' => 'unolia me --json kind,name',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $token = $this->fetch(new CurrentToken);
        $principal = $this->fetch(new CurrentAuthenticated);

        $kind = $token['tokenable_type'] ?? 'unknown';

        $this->out()->record([
            'kind' => is_string($kind) ? $kind : 'unknown',
            'id' => $principal['id'] ?? null,
            'name' => $principal['name'] ?? null,
            'email' => $principal['email'] ?? null,
            'token_name' => $token['name'] ?? null,
            'scopes' => $token['scopes'] ?? [],
            'expires_at' => $token['expires_at'] ?? null,
            'host' => $this->runtime()->host(),
        ], [
            'kind' => 'Kind',
            'id' => 'Id',
            'name' => 'Name',
            'email' => 'Email',
            'token_name' => 'Token',
            'scopes' => 'Abilities',
            'expires_at' => 'Expires',
            'host' => 'Host',
        ]);

        return ExitCode::Ok;
    }
}
