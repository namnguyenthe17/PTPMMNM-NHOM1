#!/usr/bin/env bash
set -euo pipefail
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
case "${1:-}" in
 check) bash "$root/scripts/check-env.sh" ;;
 test) php "$root/tests/run.php" && php "$root/tests/accounts.php" && php "$root/tests/submissions.php" && php "$root/tests/btl1.php" ;;
 run) [[ -f "$root/storage/data.json" || -n "${FLAGFLOW_DB_DSN:-}" ]] || { echo 'Trước tiên chạy: php scripts/setup.php'; exit 2; }; php -S 127.0.0.1:8104 -t "$root/public" "$root/public/router.php" ;;
 demo) php -S 127.0.0.1:8105 -t "$root/demo" ;;
 *) echo 'Usage: bash lab.sh {check|test|run|demo}'; exit 2 ;;
esac
