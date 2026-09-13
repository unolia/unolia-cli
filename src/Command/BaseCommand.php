<?php

declare(strict_types=1);

namespace Unolia\Cli\Command;

use Saloon\Http\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Format;
use Unolia\Cli\Console\Groups;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Console\Table\Page;
use Unolia\Cli\Context\Context;
use Unolia\Cli\Context\LocalState;
use Unolia\Cli\Context\Need;
use Unolia\Cli\Runtime;
use Unolia\Cli\Support\Duration;

/**
 * What every Unolia command inherits: the runtime, the face, the output layer, the
 * context, and the global options read in one place.
 */
abstract class BaseCommand extends Command
{
    protected InputInterface $input;

    public function __construct(protected readonly Runtime $runtime)
    {
        parent::__construct();
    }

    /** The Symfony name, with colons. The display name replaces them with spaces. */
    abstract protected function canonical(): string;

    abstract protected function handle(InputInterface $input): ExitCode;

    /**
     * Label to full command line, printed under EXAMPLES.
     *
     * @return array<string, string>
     */
    public function examples(): array
    {
        return [];
    }

    public function learnMore(): ?string
    {
        return 'https://unolia.com/docs/cli/'.str_replace(':', '-', $this->canonical());
    }

    public function mutates(): bool
    {
        return false;
    }

    protected function configure(): void
    {
        $this->setName($this->canonical());

        $aliases = Groups::aliasesFor($this->canonical());

        if ($aliases !== []) {
            $this->setAliases($aliases);
        }

        $this->define();
    }

    /** Subclasses declare their own arguments and options here. */
    protected function define(): void {}

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;

        return $this->handle($input)->value;
    }

    protected function runtime(): Runtime
    {
        return $this->runtime;
    }

    protected function face(): Face
    {
        return $this->runtime->face();
    }

    protected function out(): Out
    {
        return $this->runtime->out();
    }

    protected function ask(): Ask
    {
        return $this->runtime->ask();
    }

    protected function api(): Client
    {
        return $this->runtime->api();
    }

    protected function context(Need $need = Need::None): Context
    {
        return $this->runtime->context()->resolve($need);
    }

    protected function local(): LocalState
    {
        return $this->runtime->context()->localState();
    }

    protected function wantsJson(): bool
    {
        return $this->face()->format === Format::Json;
    }

    protected function format(): Format
    {
        return $this->face()->format;
    }

    protected function structured(): bool
    {
        return $this->face()->format->isStructured();
    }

    protected function yes(): bool
    {
        return $this->face()->yes;
    }

    protected function dryRun(): bool
    {
        return $this->face()->dryRun;
    }

    protected function paginate(): bool
    {
        return $this->face()->paginate;
    }

    protected function limit(): int
    {
        $value = $this->optionString('limit');
        $limit = $value === null ? 30 : (int) $value;

        if ($limit < 1 || $limit > 100) {
            throw CliError::usage('--limit must be between 1 and 100');
        }

        return $limit;
    }

    protected function optionString(string $name): ?string
    {
        if (! $this->input->hasOption($name)) {
            return null;
        }

        /** @var mixed $value */
        $value = $this->input->getOption($name);

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return is_int($value) ? (string) $value : null;
    }

    protected function optionInt(string $name): ?int
    {
        $value = $this->optionString($name);

        if ($value === null) {
            return null;
        }

        if (! ctype_digit($value)) {
            throw CliError::usage(sprintf('--%s must be a number', $name));
        }

        return (int) $value;
    }

    protected function optionBool(string $name): bool
    {
        return $this->input->hasOption($name) && $this->input->getOption($name) === true;
    }

    /**
     * @return list<string>
     */
    protected function optionList(string $name): array
    {
        if (! $this->input->hasOption($name)) {
            return [];
        }

        /** @var mixed $values */
        $values = $this->input->getOption($name);
        $list = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected function argumentString(string $name): ?string
    {
        if (! $this->input->hasArgument($name)) {
            return null;
        }

        /** @var mixed $value */
        $value = $this->input->getArgument($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function argumentList(string $name): array
    {
        if (! $this->input->hasArgument($name)) {
            return [];
        }

        /** @var mixed $values */
        $values = $this->input->getArgument($name);
        $list = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected function duration(string $option, int $default): int
    {
        return Duration::parse($this->optionString($option), $default);
    }

    /**
     * The query every list command sends: its own filters plus the page size.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function listQuery(array $filters = []): array
    {
        return array_merge($filters, ['per_page' => $this->limit(), 'page' => $this->pageNumber()]);
    }

    /** The page asked for with --page, null for the first. */
    protected function pageNumber(): ?int
    {
        $value = $this->optionString('page');

        if ($value === null) {
            return null;
        }

        if (! ctype_digit($value) || (int) $value < 1) {
            throw CliError::usage('--page must be a whole number from 1');
        }

        return (int) $value === 1 ? null : (int) $value;
    }

    /**
     * The rows of a list endpoint, one page by default and every page under
     * --paginate. Where the page stands is left with Out, so the table that
     * follows can say there is more and how to see it.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(Request $request): array
    {
        if ($this->paginate()) {
            $paginator = $this->api()->paginate($request);
            $paginator->setPerPageLimit($this->limit());
            $this->out()->paged(null);

            return $paginator->rows();
        }

        $body = $this->body($request);
        $rows = [];

        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        $this->out()->paged(Page::fromMeta(is_array($body['meta'] ?? null) ? $body['meta'] : [], count($rows)));

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collection(Request $request): array
    {
        $data = $this->api()->send($request)->json('data');
        $rows = [];

        foreach (is_array($data) ? $data : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * The `data` object of a single record endpoint.
     *
     * @return array<string, mixed>
     */
    protected function fetch(Request $request): array
    {
        $data = $this->api()->send($request)->json('data');

        if (! is_array($data)) {
            throw CliError::notFound('the API answered without a record');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * The whole body, for endpoints whose meta matters.
     *
     * @return array<string, mixed>
     */
    protected function body(Request $request): array
    {
        $body = $this->api()->send($request)->json();

        /** @var array<string, mixed> $body */
        return is_array($body) ? $body : [];
    }

    /**
     * Show what is about to happen, then ask. Under --dry-run nothing is asked and
     * the caller stops, which is what makes --dry-run safe by construction.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, string>  $labels
     */
    protected function confirmOrPlan(string $question, array $plan = [], array $labels = []): bool
    {
        if ($plan !== [] && ! $this->structured()) {
            $this->out()->record($plan, $labels);
        }

        if ($this->dryRun()) {
            return false;
        }

        return $this->ask()->confirm($question);
    }

    /** A URL is the answer: plain on the table face, an object for a script. */
    protected function printUrl(string $url): void
    {
        if ($this->structured()) {
            $this->out()->record(['url' => $url]);

            return;
        }

        $this->out()->raw($url."\n");
    }

    /** True when the pipe face should stay quiet, used by watchers on the table face. */
    protected function live(): bool
    {
        return $this->face()->format === Format::Table;
    }
}
