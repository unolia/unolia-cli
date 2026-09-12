<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Auth;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Core\CurrentToken;
use Unolia\Cli\Auth\Authenticator;
use Unolia\Cli\Auth\DeviceFlow;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

/**
 * Change what the stored token may do: sign in again through the browser with the new
 * scope set, then revoke the old token. The way gh auth refresh works.
 */
final class RefreshCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'auth:refresh';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Sign in again with more or fewer scopes');
    }

    protected function define(): void
    {
        $this->addOption('scopes', null, InputOption::VALUE_REQUIRED, 'Scopes to add, comma separated');
        $this->addOption('remove-scopes', null, InputOption::VALUE_REQUIRED, 'Scopes to drop, comma separated');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host whose token to refresh');
    }

    public function mutates(): bool
    {
        return true;
    }

    public function examples(): array
    {
        return [
            'Add a scope the API asked for' => 'unolia auth refresh --scopes deployment:write',
            'Drop one' => 'unolia auth refresh --remove-scopes env:read',
            'Same scopes, new token' => 'unolia auth refresh',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $host = $this->optionString('host') ?? $this->runtime()->host();
        $hosts = $this->runtime()->hosts();
        $source = $hosts->tokenSource($host);

        if ($source !== null && $source !== 'hosts.json') {
            throw CliError::usage(
                sprintf('the token comes from %s, so there is nothing stored here to refresh', $source),
                'Unset the variable and run unolia login.',
            );
        }

        $added = $this->scopeList('scopes');
        $removed = $this->scopeList('remove-scopes');
        $oldToken = $hosts->storedToken($host);

        if ($oldToken === null && $added === []) {
            throw CliError::usage(
                sprintf('you are not logged in to %s, so there is no scope set to change', $host),
                'Run unolia login, or pass --scopes to sign in with a chosen set.',
            );
        }

        DeviceFlow::rejectWildcard($host, $added);

        if (! $this->dryRun() && ! $this->ask()->interactive()) {
            throw new CliError(
                'missing_input',
                'the browser login needs a terminal',
                ExitCode::Usage,
                'Run unolia auth refresh in a terminal, or create a token with the scopes you need in the dashboard and run unolia login --with-token < token.txt.',
                ['flag' => '--scopes', 'candidates' => []],
            );
        }

        $discovery = $this->runtime()->get(DeviceFlow::class)->discover($host);
        $stored = $oldToken === null ? [] : $this->storedScopes($host, $oldToken);
        $scopes = $this->combine($stored, $added, $removed, $discovery['default_scopes']);

        if ($this->dryRun()) {
            $this->out()->record(['host' => $host, 'scopes' => $scopes, 'revokes' => $oldToken !== null], ['host' => 'Host', 'scopes' => 'Scopes', 'revokes' => 'Revokes the current token']);

            return ExitCode::Ok;
        }

        if (! $this->structured()) {
            $this->out()->note('Scopes: '.implode(', ', $scopes));
        }

        $authenticator = $this->runtime()->get(Authenticator::class);
        $replaces = $hosts->entry($host)['token_id'] ?? null;
        $identity = $authenticator->loginWithDevice($host, $scopes, $hosts->tokenName($host) ?? $authenticator->defaultTokenName(), true, $discovery, is_string($replaces) ? $replaces : null);

        $revoked = $oldToken !== null && $authenticator->revoke($host, $oldToken);

        if ($oldToken !== null && ! $revoked) {
            $this->out()->warn('The previous token could not be revoked. Remove it at https://'.$host.'/user/api-tokens.');
        }

        if ($this->structured()) {
            $this->out()->record(array_merge($identity, ['revoked_previous' => $revoked]));

            return ExitCode::Ok;
        }

        $this->out()->info(sprintf('Refreshed the token for %s as %s (%s token)', $host, $identity['name'], $identity['kind']));

        $abilities = Authenticator::abilities($identity);

        if ($abilities !== null) {
            $this->out()->note($abilities);
        }

        return ExitCode::Ok;
    }

    /**
     * (stored plus added) minus removed. A pasted token with `*` has no list to start
     * from and the browser login cannot grant `*`, so the host's defaults stand in for
     * it when adding, and there is nothing to remove from it.
     *
     * @param  list<string>  $stored
     * @param  list<string>  $added
     * @param  list<string>  $removed
     * @param  list<string>  $defaults
     * @return list<string>
     */
    private function combine(array $stored, array $added, array $removed, array $defaults): array
    {
        if ($stored === ['*']) {
            if ($removed !== []) {
                throw CliError::usage(
                    'the current token has every ability, so there is no single scope to remove',
                    'Log out and run unolia login --scopes <list> with the scopes you want.',
                );
            }

            $this->out()->note('The current token has every ability, which the browser login cannot grant. Starting from the default scopes.');
            $stored = $defaults;
        }

        $missing = array_values(array_diff($removed, $stored));

        foreach ($missing as $scope) {
            $this->out()->warn(sprintf('%s was not among the token scopes.', $scope));
        }

        $scopes = array_values(array_unique(array_merge($stored, $added)));
        $scopes = array_values(array_diff($scopes, $removed));

        if ($scopes === []) {
            throw CliError::usage('removing those scopes would leave a token that can do nothing', 'Run unolia logout instead.');
        }

        return $scopes;
    }

    /**
     * The scopes recorded at login, or asked of the API for a token stored before
     * they were recorded. Unknown means empty, and --scopes says what to ask for.
     *
     * @return list<string>
     */
    private function storedScopes(string $host, string $token): array
    {
        $scopes = $this->runtime()->hosts()->scopes($host);

        if ($scopes !== null) {
            return $scopes;
        }

        try {
            $data = $this->runtime()->clients()->make($host, $token)->send(new CurrentToken)->json('data.scopes');
        } catch (ApiException) {
            return [];
        }

        $list = [];

        foreach (is_array($data) ? $data : [] as $scope) {
            if (is_string($scope) && $scope !== '') {
                $list[] = $scope;
            }
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    private function scopeList(string $option): array
    {
        $raw = $this->optionString($option);

        if ($raw === null) {
            return [];
        }

        $scopes = array_values(array_filter(array_map(trim(...), preg_split('/[\s,]+/', $raw) ?: []), static fn (string $scope): bool => $scope !== ''));

        if ($scopes === []) {
            throw CliError::usage(sprintf('--%s is empty', $option), 'Pass a comma separated list, such as --scopes project:read,deployment:write');
        }

        return $scopes;
    }
}
