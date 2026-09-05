<?php

declare(strict_types=1);

namespace Unolia\Cli\Local;

/**
 * What running a local tool produced. `ran` is false when the tool is not on PATH.
 */
final readonly class ProcessResult
{
    public function __construct(
        public bool $ran,
        public int $exitCode = 1,
        public string $output = '',
        public string $error = '',
    ) {}

    public static function missing(): self
    {
        return new self(false);
    }

    public function successful(): bool
    {
        return $this->ran && $this->exitCode === 0;
    }

    public function trimmed(): string
    {
        return trim($this->output);
    }
}
