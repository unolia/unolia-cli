<?php

declare(strict_types=1);

namespace Tests\Support;

use Unolia\Cli\Local\Herd;
use Unolia\Cli\Local\ProcessResult;

final class FakeHerd extends Herd
{
    /**
     * @param  list<string>  $phpVersions
     * @param  list<string>  $secured
     */
    public function __construct(
        string $cwd,
        private readonly bool $installed = true,
        private readonly bool $pro = false,
        private readonly ?string $isolated = null,
        private readonly array $phpVersions = ['8.3', '8.4'],
        private readonly array $secured = [],
    ) {
        parent::__construct($cwd);
    }

    /** @var list<string> */
    public array $ran = [];

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function isPro(): bool
    {
        return $this->pro;
    }

    public function phpVersions(): array
    {
        return $this->phpVersions;
    }

    public function isolatedVersion(string $directory): ?string
    {
        return $this->isolated;
    }

    public function securedSites(): array
    {
        return $this->secured;
    }

    public function init(string $directory, ?callable $onLine = null): ProcessResult
    {
        $this->ran[] = 'init';

        if ($onLine !== null) {
            $onLine('Installing PHP 8.3', false);
            $onLine('Linking marketing.test', false);
        }

        return new ProcessResult(true, 0);
    }

    public function isolate(string $directory, string $php, ?callable $onLine = null): ProcessResult
    {
        $this->ran[] = 'isolate '.$php;

        return new ProcessResult(true, 0);
    }

    public function link(string $directory, ?callable $onLine = null): ProcessResult
    {
        $this->ran[] = 'link';

        return new ProcessResult(true, 0);
    }

    public function secure(string $site, ?callable $onLine = null): ProcessResult
    {
        $this->ran[] = 'secure '.$site;

        return new ProcessResult(true, 0);
    }
}
