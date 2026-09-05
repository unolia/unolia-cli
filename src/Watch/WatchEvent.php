<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

/**
 * One thing that happened, in both faces at once: a line for a person, an object for a script.
 */
final readonly class WatchEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload,
        public string $line,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(['event' => $this->type], $this->payload);
    }
}
