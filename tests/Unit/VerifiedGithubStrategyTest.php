<?php

declare(strict_types=1);

use Unolia\Cli\Support\VerifiedGithubStrategy;

it('accepts a phar whose sha256 matches the checksum file, in either format', function () {
    $phar = tempnam(sys_get_temp_dir(), 'unolia');
    file_put_contents((string) $phar, 'phar bytes');
    $hash = hash('sha256', 'phar bytes');

    expect(VerifiedGithubStrategy::checksumMatches($hash."  unolia.phar\n", (string) $phar))->toBeTrue()
        ->and(VerifiedGithubStrategy::checksumMatches(strtoupper($hash), (string) $phar))->toBeTrue();

    @unlink((string) $phar);
});

it('refuses a phar whose sha256 does not match, and a checksum file with no hash in it', function () {
    $phar = tempnam(sys_get_temp_dir(), 'unolia');
    file_put_contents((string) $phar, 'phar bytes');

    expect(VerifiedGithubStrategy::checksumMatches(str_repeat('a', 64).'  unolia.phar', (string) $phar))->toBeFalse()
        ->and(VerifiedGithubStrategy::checksumMatches('not a checksum', (string) $phar))->toBeFalse();

    @unlink((string) $phar);
});
