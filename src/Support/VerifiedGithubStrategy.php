<?php

declare(strict_types=1);

namespace Unolia\Cli\Support;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use RuntimeException;

/**
 * The GitHub release strategy, plus a checksum.
 *
 * The phar is downloaded over TLS from GitHub, which says who served it, not
 * that it is the file the release workflow built. That workflow publishes
 * `unolia.phar.sha256` next to the phar, and this refuses to install a phar
 * whose hash does not match it. A release without the checksum asset is
 * refused too: the check is only worth having if it cannot be skipped.
 */
class VerifiedGithubStrategy extends GithubStrategy
{
    private ?string $checksumUrl = null;

    public function download(Updater $updater): void
    {
        parent::download($updater);

        $temp = $updater->getTempPharFile();
        $checksum = $this->fetchChecksum($updater);

        if (! self::checksumMatches($checksum, $temp)) {
            @unlink($temp);

            throw new RuntimeException('the downloaded phar does not match the checksum published with the release');
        }
    }

    /**
     * Whether a checksum file (`<sha256>  unolia.phar`, or the bare hash)
     * names the file on disk.
     */
    public static function checksumMatches(string $checksumFile, string $path): bool
    {
        if (preg_match('/\b([a-f0-9]{64})\b/i', $checksumFile, $matches) !== 1) {
            return false;
        }

        $actual = hash_file('sha256', $path);

        return $actual !== false && hash_equals(strtolower($matches[1]), strtolower($actual));
    }

    /** @param  array<mixed, mixed>  $package */
    protected function getDownloadUrl(array $package): string
    {
        $url = parent::getDownloadUrl($package);
        $this->checksumUrl = $url.'.sha256';

        return $url;
    }

    private function fetchChecksum(Updater $updater): string
    {
        if ($this->checksumUrl === null) {
            throw new RuntimeException('no release was resolved to verify against');
        }

        set_error_handler([$updater, 'throwHttpRequestException']);

        try {
            $contents = file_get_contents($this->checksumUrl);
        } finally {
            restore_error_handler();
        }

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException(sprintf('the release has no checksum at %s', $this->checksumUrl));
        }

        return $contents;
    }
}
