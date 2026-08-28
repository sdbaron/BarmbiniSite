#!/usr/bin/env bash
#
# Barmbini Besucherstatistik – Queue für die manuelle Neuberechnung.
#
# Prüft die Marker-Datei (gesetzt durch den „Neu berechnen“-Button in der
# Admin-Seite) und stößt bei Bedarf recalc.sh an. Wird minütlich per Cron
# als root ausgeführt; www-data darf nur die Marker-Datei schreiben.
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FLAG="${BARMBINI_RECALC_FLAG:-/var/lib/barmbini-stats/run/recalc.flag}"

[ -f "$FLAG" ] || exit 0

# Einmal-Ausführung: Marke zuerst entfernen, damit ein fehlgeschlagener Lauf
# nicht jede Minute erneut anläuft. Details landen in /var/log/barmbini-stats.log.
rm -f "$FLAG"
"$DIR/recalc.sh"
