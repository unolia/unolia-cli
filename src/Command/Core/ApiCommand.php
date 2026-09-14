<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Core;

use Saloon\Enums\Method;
use Saloon\Http\Response;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Core\RawRequest;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Renderers\JsonRenderer;
use Unolia\Cli\Support\Stdin;

/**
 * An authenticated request to the Unolia API, the way `gh api` works.
 */
final class ApiCommand extends BaseCommand
{
    protected function canonical(): string
    {
        return 'api';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Make an authenticated request to the Unolia API');
    }

    protected function define(): void
    {
        $this->addArgument('endpoint', InputArgument::REQUIRED, 'A path such as v2/websites, or a full URL on this host');
        $this->addOption('method', 'X', InputOption::VALUE_REQUIRED, 'HTTP method, GET by default');
        $this->addOption('raw-field', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A key=value string field');
        $this->addOption('field', 'F', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A typed key=value field, @file and @- read a file or stdin');
        $this->addOption('header', 'H', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extra header, "Key: value"');
        $this->addOption('input', null, InputOption::VALUE_OPTIONAL, 'Send this file as the body, --input or --input=- reads stdin');
        $this->addOption('include', 'i', InputOption::VALUE_NONE, 'Print the status line and headers');
        $this->addOption('slurp', null, InputOption::VALUE_NONE, 'With --paginate, merge every data array into one');
    }

    public function examples(): array
    {
        return [
            'Read a list' => 'unolia api v2/websites',
            'Filter the answer' => 'unolia api v2/websites --jq \'.data[].domain\'',
            'Send a body' => 'unolia api v2/websites/118/deployments -X POST -F dry_run=true',
            'Send a file' => 'unolia api v2/websites/118/deployments --input=body.json',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        [$path, $pathQuery] = $this->normalize((string) $this->argumentString('endpoint'));

        $fields = $this->fields();
        $method = $this->method($fields);
        $isRead = in_array($method, [Method::GET, Method::HEAD], true);
        $body = $this->requestBody($fields, $isRead);

        $request = new RawRequest(
            method: $method,
            path: $path,
            parameters: array_merge($pathQuery, $isRead ? $fields : []),
            extraHeaders: $this->headers(),
            rawBody: $body,
        );

        $client = $this->client();

        if ($this->paginate()) {
            return $this->paginated($client, $request);
        }

        $response = $client->send($request);

        $this->printHeaders($response);
        $this->printBody($response);

        return ExitCode::Ok;
    }

    private function client(): Client
    {
        $host = $this->runtime()->host();
        $kind = $this->runtime()->hosts()->entry($host)['kind'] ?? 'unknown';

        return $this->runtime()->clients()->raw(
            $host,
            $this->runtime()->hosts()->tokenFor($host),
            is_string($kind) ? $kind : 'unknown',
        );
    }

    private function paginated(Client $client, RawRequest $request): ExitCode
    {
        $paginator = $client->paginate($request);
        $paginator->setPerPageLimit($this->limit());

        if ($this->optionBool('slurp')) {
            $this->out()->document($paginator->rows());

            return ExitCode::Ok;
        }

        foreach ($paginator as $response) {
            $this->printBody($response);
        }

        return ExitCode::Ok;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function normalize(string $endpoint): array
    {
        $endpoint = trim($endpoint);
        $query = [];

        if (preg_match('#^https?://#i', $endpoint) === 1) {
            $parts = parse_url($endpoint);
            $host = is_array($parts) ? ($parts['host'] ?? '') : '';

            if ($host !== $this->runtime()->host()) {
                throw CliError::usage(
                    sprintf('%s is not on %s', $endpoint, $this->runtime()->host()),
                    'The api command only talks to the configured host.',
                );
            }

            $endpoint = is_array($parts) ? ($parts['path'] ?? '/') : '/';
            $endpoint .= isset($parts['query']) && is_string($parts['query']) ? '?'.$parts['query'] : '';
        }

        if (str_contains($endpoint, '?')) {
            [$endpoint, $queryString] = explode('?', $endpoint, 2);
            parse_str($queryString, $parsed);

            foreach ($parsed as $key => $value) {
                if (is_string($value)) {
                    $query[(string) $key] = $value;
                }
            }
        }

        $endpoint = ltrim($endpoint, '/');

        if ($endpoint === '') {
            throw CliError::usage('the endpoint is empty', 'Try: unolia api v2/teams');
        }

        $path = match (true) {
            str_starts_with($endpoint, 'api/') => $endpoint,
            preg_match('/^v\d+(\/|$)/', $endpoint) === 1 => 'api/'.$endpoint,
            default => 'api/v2/'.$endpoint,
        };

        return [$path, $query];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function method(array $fields): Method
    {
        $method = $this->optionString('method');

        if ($method === null) {
            return $fields === [] && ! $this->readsBody() ? Method::GET : Method::POST;
        }

        $method = strtoupper($method);
        $case = Method::tryFrom($method);

        if ($case === null) {
            throw CliError::usage(sprintf('%s is not an HTTP method', $method));
        }

        return $case;
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        $fields = [];

        foreach ($this->optionList('raw-field') as $pair) {
            [$key, $value] = $this->split($pair, '-f');
            $fields[$key] = $value;
        }

        foreach ($this->optionList('field') as $pair) {
            [$key, $value] = $this->split($pair, '-F');
            $fields[$key] = $this->typed($value);
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function requestBody(array $fields, bool $isRead): ?string
    {
        if ($this->readsBody()) {
            $file = $this->optionString('input');

            return $file === null || $file === '-'
                ? $this->runtime()->get(Stdin::class)->read()
                : $this->readFile($file);
        }

        if ($isRead || $fields === []) {
            return null;
        }

        return JsonRenderer::encode($fields);
    }

    /** True when --input was passed at all, with or without a value. */
    private function readsBody(): bool
    {
        return $this->input->hasParameterOption('--input', true);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];

        foreach ($this->optionList('header') as $header) {
            if (! str_contains($header, ':')) {
                throw CliError::usage(sprintf('%s is not a header', $header), 'Use -H "Key: value".');
            }

            [$key, $value] = explode(':', $header, 2);
            $headers[trim($key)] = trim($value);
        }

        return $headers;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $pair, string $flag): array
    {
        if (! str_contains($pair, '=')) {
            throw CliError::usage(sprintf('%s %s is not a key=value pair', $flag, $pair));
        }

        [$key, $value] = explode('=', $pair, 2);

        return [trim($key), $value];
    }

    private function typed(string $value): mixed
    {
        if ($value === '@-') {
            return trim($this->runtime()->get(Stdin::class)->read());
        }

        if (str_starts_with($value, '@')) {
            return $this->readFile(substr($value, 1));
        }

        return match (true) {
            $value === 'true' => true,
            $value === 'false' => false,
            $value === 'null' => null,
            ctype_digit(ltrim($value, '-')) && $value !== '-' => (int) $value,
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }

    private function readFile(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw CliError::usage(sprintf('could not read %s', $path));
        }

        return $contents;
    }

    private function printHeaders(Response $response): void
    {
        if (! $this->optionBool('include')) {
            return;
        }

        $lines = [sprintf('HTTP/1.1 %d', $response->status())];

        foreach ($response->headers()->all() as $name => $value) {
            $lines[] = sprintf('%s: %s', $name, is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value);
        }

        $this->out()->line(implode("\n", $lines));
        $this->out()->line('');
    }

    private function printBody(Response $response): void
    {
        $body = $response->body();

        if (trim($body) === '') {
            return;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);

        if ($decoded === null && trim($body) !== 'null') {
            $this->out()->raw($body);

            return;
        }

        $this->out()->document($decoded);
    }
}
