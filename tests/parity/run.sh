#!/usr/bin/env bash
# Runs the parity harness for two apps on copies of the same database and diffs the results.
#   tests/parity/run.sh [source db|empty] [kind-a:root-a] [kind-b:root-b]
# Defaults: the production copy, the pinned old app against this checkout.
set -euo pipefail
cd "$(dirname "$0")/../.."

source_db=${1:-var/parity/prod.db}
a=${2:-legacy:var/parity/legacy}
b=${3:-symfony:.}
out=var/parity/out

php8.4 tests/parity/parity.php "${a%%:*}" "${a#*:}" "$source_db" "$out/a"
php8.4 tests/parity/parity.php "${b%%:*}" "${b#*:}" "$source_db" "$out/b"

if diff -ru --exclude=work "$out/a" "$out/b" > "$out/diff.txt"; then
    echo "No differences."
else
    echo "Differences: $(grep -c '^[-+][^-+]' "$out/diff.txt") changed lines, see $out/diff.txt"
    exit 1
fi
