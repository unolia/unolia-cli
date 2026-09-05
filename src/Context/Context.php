<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

use Unolia\Cli\Console\CliError;

/**
 * The team, project and website one invocation acts on, plus where each value came from.
 */
final readonly class Context
{
    /**
     * @param  array<string, string>  $sources
     */
    public function __construct(
        public ?string $team = null,
        public ?int $project = null,
        public ?int $website = null,
        public ?string $environment = null,
        public array $sources = [],
        public ?string $configPath = null,
        public ?string $gitRoot = null,
        public ?string $remote = null,
        public ?string $branch = null,
    ) {}

    public function sourceOf(string $key): ?string
    {
        return $this->sources[$key] ?? null;
    }

    public function requireWebsite(): int
    {
        return $this->website ?? throw CliError::unlinkedDirectory([], $this->remote);
    }

    public function requireProject(): int
    {
        return $this->project ?? throw CliError::usage(
            'no project in context',
            'Pass --project <id>, or run unolia init.',
        );
    }
}
