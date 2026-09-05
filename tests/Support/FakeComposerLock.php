<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Local\ComposerLock;

final class FakeComposerLock extends ComposerLock
{
    /**
     * @param  array<string, string|null>  $packages
     */
    public function __construct(
        private readonly array $packages = [],
        private readonly ?string $composer = '2.8.4',
    ) {}

    public function packageVersion(string $directory, string $package): ?string
    {
        return $this->packages[$package] ?? null;
    }

    public function composerVersion(): ?string
    {
        return $this->composer;
    }
}
