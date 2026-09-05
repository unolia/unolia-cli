<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

/**
 * What installing the connector into one agent will do, decided before anything is
 * touched so the plan can be shown, confirmed or printed under --dry-run.
 */
final readonly class InstallStep
{
    /**
     * @param  list<string>  $command
     */
    private function __construct(
        public Agent $agent,
        public string $action,
        public array $command = [],
        public string $path = '',
        public string $displayPath = '',
        public string $reason = '',
    ) {}

    /**
     * @param  list<string>  $command
     */
    public static function run(Agent $agent, array $command): self
    {
        return new self($agent, 'run', command: $command);
    }

    public static function write(Agent $agent, string $path, string $displayPath): self
    {
        return new self($agent, 'write', path: $path, displayPath: $displayPath);
    }

    public static function skip(Agent $agent, string $reason): self
    {
        return new self($agent, 'skip', reason: $reason);
    }

    /** The command as the summary names it, its first words only. */
    public function commandLabel(): string
    {
        return implode(' ', array_slice($this->command, 0, 3));
    }

    /** One line for the table face. */
    public function describe(): string
    {
        return match ($this->action) {
            'run' => 'run '.$this->commandLabel(),
            'write' => 'write '.$this->displayPath,
            default => 'skip, '.$this->reason,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'agent' => $this->agent->value,
            'name' => $this->agent->displayName(),
            'action' => $this->action,
            'command' => $this->command === [] ? null : $this->command,
            'path' => $this->path === '' ? null : $this->path,
            'reason' => $this->reason === '' ? null : $this->reason,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
