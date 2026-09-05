<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * Everything `configure herd` learned from production, ready to become a herd.yml.
 */
final readonly class HerdPlanInput
{
    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $name,
        public ?string $php = null,
        public ?bool $secured = null,
        public array $aliases = [],
        public ?string $databaseEngine = null,
        public ?string $databaseVersion = null,
        public bool $withServices = false,
        public ?string $forgeDomain = null,
        public ?int $forgeServerId = null,
        public ?int $forgeSiteId = null,
    ) {}

    public function hasForge(): bool
    {
        return $this->forgeDomain !== null && $this->forgeServerId !== null && $this->forgeSiteId !== null;
    }
}
