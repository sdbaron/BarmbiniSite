#!/usr/bin/env bash
#
# Barmbini Besucherstatistik – Wrapper für process.php (für Cron).
#
# Aufruf:  /root/barmbini-stats/process.sh
# Ruft:    process.php im selben Verzeichnis auf (PHP CLI).
# Umgebungsvariablen (BARMBINI_*) werden an process.php durchgereicht.
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -n "${PHP_BIN:-}" ]; then
	PHP_BIN="$PHP_BIN"
elif command -v php >/dev/null 2>&1; then
	PHP_BIN="$(command -v php)"
else
	PHP_BIN="/usr/bin/php"
fi

exec "$PHP_BIN" "$DIR/process.php" "$@"
