#!/usr/bin/env bash
#
# Barmbini Besucherstatistik – Installation (idempotent).
#
# - legt Verzeichnisse an (/root/barmbini-stats, /var/lib/barmbini-stats/stats)
# - kopiert process.php / process.sh nach /root/barmbini-stats
# - richtet logrotate für das nginx-Zugriffslog ein (rotate 7, delaycompress)
#   und passt ggf. /etc/logrotate.d/nginx an (mit Backup)
# - installiert den Cron-Eintrag in /etc/cron.d/barmbini-stats (täglich 07:15)
# - optional: --test führt einen einmaligen Testlauf aus
#
# Aufruf:  ./install.sh          # als root
#          ./install.sh --test   # inkl. einmaligem Testlauf
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Bitte als root ausführen (sudo ./install.sh)." >&2
	exit 1
fi

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST=/root/barmbini-stats
STATS_DIR=/var/lib/barmbini-stats/stats
RUN_LOG=/var/log/barmbini-stats.log
BACKUP_DIR="$DEST/backups"
CRON_FILE=/etc/cron.d/barmbini-stats
NGINX_LOGROTATE=/etc/logrotate.d/nginx

echo "== Barmbini Besucherstatistik – Installation =="

# 1. Verzeichnisse + Lauf-Log
mkdir -p "$DEST" "$STATS_DIR" "$BACKUP_DIR"
touch "$RUN_LOG"
chmod 750 "$STATS_DIR"
echo "[1/4] Verzeichnisse ok ($DEST, $STATS_DIR)"

# 2. Skripte installieren (überspringen, wenn bereits im Zielverzeichnis)
if [ "$SRC" != "$DEST" ]; then
	cp "$SRC/process.php" "$SRC/process.sh" "$DEST/"
	chmod +x "$DEST/process.sh"
	echo "[2/4] Skripte nach $DEST kopiert"
else
	chmod +x "$DEST/process.sh"
	echo "[2/4] Skripte bereits im Zielverzeichnis (kein Kopieren nötig)"
fi

# 3. logrotate: barmbini_access.log täglich, rotate 7, delaycompress
if [ -f "$NGINX_LOGROTATE" ] && grep -q '/var/log/nginx/\*.log' "$NGINX_LOGROTATE"; then
	# nginx-logrotate rotiert die Datei bereits → nur Aufbewahrung anpassen.
	cp "$NGINX_LOGROTATE" "$BACKUP_DIR/nginx.logrotate.$(date +%Y%m%d-%H%M%S)"
	sed -i -E 's/^([[:space:]]*)rotate [0-9]+/\1rotate 7/' "$NGINX_LOGROTATE"
	if ! grep -q 'delaycompress' "$NGINX_LOGROTATE"; then
		sed -i -E 's/^([[:space:]]*)compress/\1compress\n\1delaycompress/' "$NGINX_LOGROTATE"
	fi
	echo "[3/4] logrotate: /etc/logrotate.d/nginx angepasst (rotate 7, delaycompress) – Backup in $BACKUP_DIR"
else
	cp "$SRC/logrotate-barmbini-stats" /etc/logrotate.d/barmbini-stats
	echo "[3/4] logrotate: /etc/logrotate.d/barmbini-stats installiert"
fi

# 4. Cron (täglich 07:15, nach logrotate)
if [ -f "$CRON_FILE" ]; then
	echo "[4/4] Cron bereits vorhanden: $CRON_FILE"
else
	printf '15 7 * * * root %s/process.sh >/dev/null 2>&1\n' "$DEST" > "$CRON_FILE"
	chmod 644 "$CRON_FILE"
	echo "[4/4] Cron installiert: $CRON_FILE (täglich 07:15)"
fi

if [ "${1:-}" = "--test" ]; then
	echo ""
	echo "== Testlauf =="
	"$DEST/process.sh"
fi

echo ""
echo "Fertig. Details und Validierung: server-config/barmbini-stats/README.md"
