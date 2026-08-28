#!/usr/bin/env bash
#
# Barmbini Besucherstatistik – Installation (idempotent).
#
# - legt Verzeichnisse an (/root/barmbini-stats, /var/lib/barmbini-stats/stats)
# - kopiert process.php / process.sh / recalc.sh / recalc-queue.sh nach /root/barmbini-stats
# - richtet logrotate für das nginx-Zugriffslog ein (rotate 7, delaycompress)
#   und passt ggf. /etc/logrotate.d/nginx an (mit Backup)
# - installiert die Cron-Einträge in /etc/cron.d/barmbini-stats (täglich 07:15 + Queue minütlich)
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
EXCLUDED_IPS_FILE=/var/lib/barmbini-stats/excluded-ips.conf
RUN_DIR=/var/lib/barmbini-stats/run
RUN_LOG=/var/log/barmbini-stats.log
BACKUP_DIR="$DEST/backups"
CRON_FILE=/etc/cron.d/barmbini-stats
NGINX_LOGROTATE=/etc/logrotate.d/nginx

echo "== Barmbini Besucherstatistik – Installation =="

# 1. Verzeichnisse + Lauf-Log
mkdir -p "$DEST" "$STATS_DIR" "$BACKUP_DIR"
touch "$RUN_LOG"
# 755 + 644: WordPress laeuft als www-data und muss die Aggregate lesen koennen
# (vorher 750/root verhinderte die Anzeige im Plugin).
chmod 755 "$STATS_DIR"
chmod 644 "$STATS_DIR"/stats-*.json 2>/dev/null || true
# Ausschlussliste: www-data muss sie lesen UND schreiben koennen (Admin-Seite).
touch "$EXCLUDED_IPS_FILE"
chown www-data:www-data "$EXCLUDED_IPS_FILE"
chmod 664 "$EXCLUDED_IPS_FILE"
# Marker-Verzeichnis für den „Neu berechnen“-Button (www-data darf nur hier schreiben).
mkdir -p "$RUN_DIR"
chown www-data:www-data "$RUN_DIR"
chmod 755 "$RUN_DIR"
echo "[1/4] Verzeichnisse ok ($DEST, $STATS_DIR)"

# 2. Skripte installieren (überspringen, wenn bereits im Zielverzeichnis)
if [ "$SRC" != "$DEST" ]; then
	cp "$SRC/process.php" "$SRC/process.sh" "$SRC/recalc.sh" "$SRC/recalc-queue.sh" "$DEST/"
	chmod +x "$DEST/process.sh" "$DEST/recalc.sh" "$DEST/recalc-queue.sh"
	echo "[2/4] Skripte nach $DEST kopiert"
else
	chmod +x "$DEST/process.sh" "$DEST/recalc.sh" "$DEST/recalc-queue.sh"
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

# 4. Cron: täglicher Lauf (07:15, nach logrotate) + Neuberechnungs-Queue (minütlich)
cat > "$CRON_FILE" <<EOF
15 7 * * * root $DEST/process.sh >/dev/null 2>&1
* * * * * root $DEST/recalc-queue.sh >/dev/null 2>&1
EOF
chmod 644 "$CRON_FILE"
echo "[4/4] Cron installiert: $CRON_FILE (täglich 07:15 + Queue minütlich)"

if [ "${1:-}" = "--test" ]; then
	echo ""
	echo "== Testlauf =="
	"$DEST/process.sh"
fi

echo ""
echo "Fertig. Details und Validierung: server-config/barmbini-stats/README.md"
