<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

use Symfony\Component\Process\Process;

/**
 * What git can tell us about the current directory. Every call is best effort and
 * capped at two seconds, because a slow git must never slow a command down.
 */
class GitRemote
{
    /** @var array<string, string|null> */
    private array $cache = [];

    public function __construct(protected readonly string $cwd) {}

    public function root(): ?string
    {
        return $this->cached('root', ['rev-parse', '--show-toplevel']);
    }

    public function remote(): ?string
    {
        return $this->cached('remote', ['config', '--get', 'remote.origin.url']);
    }

    public function branch(): ?string
    {
        $branch = $this->cached('branch', ['rev-parse', '--abbrev-ref', 'HEAD']);

        return $branch === 'HEAD' ? null : $branch;
    }

    public function commit(): ?string
    {
        return $this->cached('commit', ['rev-parse', '--short', 'HEAD']);
    }

    /** owner/name read out of the remote URL, whichever form it takes. */
    public function slug(): ?string
    {
        $remote = $this->remote();

        if ($remote === null) {
            return null;
        }

        $remote = preg_replace('/\.git$/', '', $remote) ?? $remote;

        if (preg_match('#[:/]([^/:]+/[^/]+)$#', $remote, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function cached(string $key, array $arguments): ?string
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->run($arguments);
        }

        return $this->cache[$key];
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function run(array $arguments): ?string
    {
        $process = new Process(['git', ...$arguments], $this->cwd);
        $process->setTimeout(2);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output === '' ? null : $output;
    }
}
