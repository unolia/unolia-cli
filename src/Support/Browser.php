<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use Symfony\Component\Process\Process;

class Browser
{
    /** @param callable(list<string>): bool|null $runner */
    public function __construct(private $runner = null) {}

    /** Open a URL with the platform's own opener. Returns false when nothing could run it. */
    public function open(string $url, ?string $override = null): bool
    {
        $command = $this->command($url, $override);

        if ($command === null) {
            return false;
        }

        if ($this->runner !== null) {
            return ($this->runner)($command);
        }

        $process = new Process($command);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return list<string>|null
     */
    private function command(string $url, ?string $override): ?array
    {
        if ($override !== null && $override !== '') {
            return [$override, $url];
        }

        return match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $url],
            'Windows' => ['cmd', '/c', 'start', '', $url],
            'Linux', 'BSD', 'Solaris' => ['xdg-open', $url],
            default => null,
        };
    }
}
