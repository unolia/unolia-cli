#!/usr/bin/env bash
set -euo pipefail

repository=$(git rev-parse --show-toplevel)
revision=$(git rev-parse HEAD)
work=$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/unolia-cpx.XXXXXX")
trap 'rm -rf "$work"' EXIT

# Export the checked-out commit, honoring export-ignore and excluding vendor/.
# A fixed synthetic version avoids branch-name parsing and Packagist indexing.
git -C "$repository" archive --format=zip --output="$work/package.zip" "$revision"
git -C "$repository" show "$revision:composer.json" > "$work/manifest.json"
git -C "$repository" show "$revision:bin/unolia" > "$work/expected-bin"

export HOME="$work/home"
export XDG_CONFIG_HOME="$work/config"
export COMPOSER_HOME="$work/tool-composer"
export COMPOSER_CACHE_DIR="$work/composer-cache"
export CPX_HOME="$work/cpx"
unset COMPOSER COMPOSER_VENDOR_DIR COMPOSER_BIN_DIR COMPOSER_ROOT_VERSION COMPOSER_AUTH
unset UNOLIA_TOKEN UNOLIA_API_TOKEN
mkdir -p "$HOME" "$XDG_CONFIG_HOME" "$COMPOSER_HOME" "$work/consumer"

# Keep CPX's own install separate from the package installation it will perform.
composer global require 'cpx/cpx:^2.0' --no-interaction --no-progress --prefer-dist --no-scripts --no-plugins
cpx="$COMPOSER_HOME/vendor/bin/cpx"
"$cpx" --version

export COMPOSER_HOME="$work/package-composer"
mkdir -p "$COMPOSER_HOME"
php -r '
    $package = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
    if ($package["name"] !== "unolia/unolia-cli") {
        throw new RuntimeException("Unexpected package name.");
    }
    $package["version"] = "dev-cpx-pr";
    $package["dist"] = [
        "type" => "zip",
        "url" => "file://".$argv[2],
        "reference" => $argv[3],
        "shasum" => sha1_file($argv[2]),
    ];
    $config = ["repositories" => ["pr" => ["type" => "package", "package" => $package]]];
    file_put_contents($argv[4], json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
' "$work/manifest.json" "$work/package.zip" "$revision" "$COMPOSER_HOME/config.json"

# No checkout, vendor tree, application credentials or local binary to fall back to.
cd "$work/consumer"
package='unolia/unolia-cli:dev-cpx-pr'
"$cpx" "$package" --version > "$work/version.txt"
php -r '
    $output = file_get_contents($argv[1]);
    if (! str_contains($output, "Unolia CLI ")) {
        throw new RuntimeException("CPX did not return the CLI version: ".$output);
    }
' "$work/version.txt"

php -r '
    $locks = glob($argv[1]."/unolia/unolia-cli/*/composer.lock");
    if (count($locks) !== 1) {
        throw new RuntimeException("Expected exactly one isolated CPX installation.");
    }
    $lock = json_decode(file_get_contents($locks[0]), true, flags: JSON_THROW_ON_ERROR);
    $packages = array_column($lock["packages"], null, "name");
    $package = $packages["unolia/unolia-cli"] ?? [];
    if (($package["dist"]["reference"] ?? null) !== $argv[2]
        || ($package["dist"]["url"] ?? null) !== "file://".$argv[3]) {
        throw new RuntimeException("CPX installed a different revision or package source.");
    }
    $root = dirname($locks[0]);
    $installed = $root."/vendor/unolia/unolia-cli";
    if (is_link($installed)
        || hash_file("sha256", $installed."/bin/unolia") !== hash_file("sha256", $argv[4])) {
        throw new RuntimeException("CPX did not install the exported PR binary.");
    }
    foreach (["pestphp/pest", "phpstan/phpstan", "laravel/pint"] as $developmentPackage) {
        if (isset($packages[$developmentPackage])) {
            throw new RuntimeException("Development dependencies leaked into the package install.");
        }
    }
    if (is_dir($installed."/vendor") || is_dir($installed."/tests")) {
        throw new RuntimeException("The checkout vendor tree or export-ignored tests leaked into the archive.");
    }
    printf("Verified isolated CPX package installation of %s\n", $argv[2]);
' "$CPX_HOME" "$revision" "$work/package.zip" "$work/expected-bin"

"$cpx" "$package" --help > "$work/help.txt"
"$cpx" "$package" domain list --help > "$work/domain-help.txt"
php -r '
    foreach (array_slice($argv, 1) as $file) {
        if (trim(file_get_contents($file)) === "") {
            throw new RuntimeException("CPX returned empty command help.");
        }
    }
' "$work/help.txt" "$work/domain-help.txt"

# A broken CLI must fail CI, not merely print an error and return success.
status=0
"$cpx" "$package" __cpx_missing_command__ > "$work/error.txt" 2>&1 || status=$?
if [[ "$status" != 2 ]]; then
    printf 'Expected CLI usage exit code 2 through CPX, got %s\n' "$status" >&2
    exit 1
fi
printf 'CPX version, help, command help and failure propagation passed.\n'
