<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Context\GitRemote;

/**
 * Git answers without running git.
 */
final class FakeGitRemote extends GitRemote
{
    /**
     * @param  array<string, string|null>  $answers
     */
    public function __construct(string $cwd, private readonly array $answers = [])
    {
        parent::__construct($cwd);
    }

    protected function run(array $arguments): ?string
    {
        return match (implode(' ', $arguments)) {
            'rev-parse --show-toplevel' => $this->answers['root'] ?? $this->cwd,
            'config --get remote.origin.url' => $this->answers['remote'] ?? null,
            'rev-parse --abbrev-ref HEAD' => $this->answers['branch'] ?? null,
            'rev-parse --short HEAD' => $this->answers['commit'] ?? null,
            default => null,
        };
    }
}
