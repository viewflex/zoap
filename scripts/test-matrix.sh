#!/usr/bin/env bash
#
# Run the test suite on each local PHP version, with the newest and the lowest
# laminas packages Composer can install, without touching this directory's vendor.
#
# Usage: scripts/test-matrix.sh [php-version ...]     e.g. scripts/test-matrix.sh 8.0 8.5
#
# Requires Laravel Herd PHP binaries (php80 ... php85), or set PHP_BIN_DIR.

set -uo pipefail

PACKAGE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN_DIR="${PHP_BIN_DIR:-$HOME/Library/Application Support/Herd/bin}"
COMPOSER="${COMPOSER_BIN:-$PHP_BIN_DIR/composer}"

# PHP version => Testbench (Laravel) version used for that PHP.
testbench_for() {
    case "$1" in
        8.0) echo "^6.0" ;;   # Laravel 8
        8.1) echo "^7.0" ;;   # Laravel 9
        8.2) echo "^8.0" ;;   # Laravel 10
        8.3) echo "^9.0" ;;   # Laravel 11
        8.4) echo "^10.0" ;;  # Laravel 12
        8.5) echo "^11.0" ;;  # Laravel 13
        *) return 1 ;;
    esac
}

VERSIONS=("$@")
[ ${#VERSIONS[@]} -eq 0 ] && VERSIONS=(8.0 8.1 8.2 8.3 8.4 8.5)

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

RESULTS=()
STATUS=0

for version in "${VERSIONS[@]}"; do
    testbench="$(testbench_for "$version")" || { echo "Unsupported PHP version: $version"; exit 1; }
    php=("$PHP_BIN_DIR/php${version/./}" -d pcre.jit=0)

    for deps in newest lowest; do
        dir="$WORK_DIR/php$version-$deps"
        mkdir -p "$dir"
        (cd "$PACKAGE_DIR" && git ls-files -z --cached --others --exclude-standard | xargs -0 -I{} rsync -R "{}" "$dir/")

        log="$dir/run.log"
        (
            cd "$dir" || exit 1
            "${php[@]}" "$COMPOSER" config policy.advisories.block false &&
            "${php[@]}" "$COMPOSER" require --dev --no-update "orchestra/testbench:$testbench" &&
            "${php[@]}" "$COMPOSER" update --no-interaction --no-progress &&
            if [ "$deps" = lowest ]; then
                "${php[@]}" "$COMPOSER" update 'laminas/*' --prefer-lowest --with-dependencies --no-interaction --no-progress
            fi &&
            "${php[@]}" vendor/bin/phpunit --testsuite Unit,Feature --colors=never
        ) > "$log" 2>&1
        result=$?

        laminas="$(cd "$dir" && "${php[@]}" "$COMPOSER" show laminas/laminas-soap 2>/dev/null | awk '/^versions/ {print $NF}')"
        laravel="$(cd "$dir" && "${php[@]}" "$COMPOSER" show laravel/framework 2>/dev/null | awk '/^versions/ {print $NF}')"
        summary="$(grep -E '^(OK|Tests:)' "$log" | tail -1 | sed 's/\x1b\[[0-9;]*m//g')"

        if [ $result -eq 0 ]; then
            RESULTS+=("PASS  PHP $version  $deps  laminas-soap ${laminas:-?}  laravel ${laravel:-?}  $summary")
        else
            STATUS=1
            RESULTS+=("FAIL  PHP $version  $deps  laminas-soap ${laminas:-?}  laravel ${laravel:-?}  $summary")
            echo "---- PHP $version ($deps) failed; log tail:"
            tail -25 "$log"
        fi
    done
done

echo
printf '%s\n' "${RESULTS[@]}"
exit $STATUS
