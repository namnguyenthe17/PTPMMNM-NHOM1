#!/usr/bin/env bash
set -uo pipefail
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)" || exit 2
for tool in php git; do command -v "$tool" >/dev/null || { echo "MISSING tool $tool"; exit 2; }; echo "OK tool $tool"; done
for file in public/index.php app/Policy.php app/Store.php; do [[ -f "$root/$file" ]] || { echo "MISSING file $file"; exit 2; }; done
if php -l "$root/public/index.php" >/dev/null; then echo 'CHECK_ENV=PASS'; else exit 1; fi
