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

    /** The origin URL, without any credential it carries. */
    public function remote(): ?string
    {
        $remote = $this->cached('remote', ['config', '--get', 'remote.origin.url']);

        return $remote === null ? null : self::withoutCredentials($remote);
    }

    /**
     * A clone made with a token (`https://x-access-token:ghp_…@github.com/…`, a
     * CI job token) keeps it in remote.origin.url. It is no part of where the
     * code lives, so it is dropped before the URL is sent, printed or logged.
     * The SSH form `git@host:path` names a user and no secret, and is left alone.
     */
    public static function withoutCredentials(string $url): string
    {
        $url = trim($url);

        return preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', $url) ?? $url;
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

    /** The full sha of HEAD. */
    public function head(): ?string
    {
        return $this->cached('head', ['rev-parse', 'HEAD']);
    }

    /** Whether this checkout has the commit at all; a sha from another repository does not. */
    public function knows(string $sha): bool
    {
        return $this->run(['rev-parse', '--verify', '--quiet', $sha.'^{commit}']) !== null;
    }

    /** How many commits $to has that $from does not, or null when git cannot tell. */
    public function countBetween(string $from, string $to): ?int
    {
        $count = $this->run(['rev-list', '--count', $from.'..'.$to]);

        return $count !== null && ctype_digit($count) ? (int) $count : null;
    }

    /** Commits on HEAD that the upstream branch does not have yet, or null without an upstream. */
    public function unpushed(): ?int
    {
        return $this->countBetween('@{u}', 'HEAD');
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
