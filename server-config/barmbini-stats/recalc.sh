#!/usr/bin/env bash
#
# Barmbini Besucherstatistik – Neuberechnung der letzten Tage.
#
# Verarbeitet die noch vorhandenen Roh-Logs (live, .1, .2.gz … .7.gz)
# erneut mit den aktuellen Filtern (z. B. IP-Ausschluss). Wird von
# recalc-queue.sh aufgerufen, sobald die Marker-Datei gesetzt wurde.
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

LOG_DIR="${BARMBINI_LOG_DIR:-/var/log/nginx}"
BASE="${BARMBINI_LOG_BASE:-barmbini_access.log}"
STATS_DIR="${BARMBINI_STATS_DIR:-/var/lib/barmbini-stats/stats}"
MAX_DAYS="${BARMBINI_RECALC_MAX_DAYS:-7}"

# Serielle Ausführung (gleiche Lock wie der tägliche Lauf).
exec 9>/run/barmbini-stats.lock
flock 9

# Live-Log (heute, soweit vorhanden) und .1 (gestern, unkomprimiert).
for f in "$LOG_DIR/$BASE" "$LOG_DIR/$BASE.1"; do
	[ -f "$f" ] || continue
	BARMBINI_LOG_INPUT="$f" BARMBINI_STATS_DIR="$STATS_DIR" "$PHP_BIN" "$DIR/process.php"
done

# Ältere rotierte Logs (.2.gz … .N.gz).
for i in $(seq 2 "$MAX_DAYS"); do
	f="$LOG_DIR/$BASE.$i.gz"
	[ -f "$f" ] || continue
	BARMBINI_LOG_INPUT="$f" BARMBINI_STATS_DIR="$STATS_DIR" "$PHP_BIN" "$DIR/process.php"
done

echo "Recalc OK"
