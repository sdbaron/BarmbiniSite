#!/usr/bin/env bash
# =============================================================================
# Barmbini Fetch – Server-Daten abrufen und lokal einspielen
# Plattformunabhaengiges Skript (macOS, Linux, Windows/Git Bash)
#
# Verwendung:
#   ./fetch.sh                  # Modus B: Nur Code holen
#   ./fetch.sh --full           # Modus A: Vollabgleich mit DB + Uploads
#   ./fetch.sh --full --force   # Modus A: Alterspruefung deaktiviert
#   ./fetch.sh --nobackup       # Ohne lokales Backup
#   ./fetch.sh --full --nobrowser # Modus A, kein Browser oeffnen
#
# Voraussetzungen:
#   - bash >= 4.0
#   - ssh, scp, tar, curl im PATH
#   - Lokale WordPress-Installation (Pfad in Konfiguration anpassbar)
#   - SSH-Schluessel zum Server hinterlegt
#   - wp-cli lokal verfuegbar (fuer DB-Import und search-replace)
# =============================================================================

set -euo pipefail

# -------------------------------------------------------------------
# OS-Erkennung
# -------------------------------------------------------------------
detect_os() {
    case "$(uname -s)" in
        Linux*)  echo linux ;;
        Darwin*) echo macos ;;
        CYGWIN*|MINGW*|MSYS*) echo windows ;;
        *)       echo unknown ;;
    esac
}

OS=$(detect_os)

# -------------------------------------------------------------------
# Konfiguration – bei Bedarf anpassen
# -------------------------------------------------------------------

# --- Lokale Pfade ---
if [ "$OS" = "windows" ]; then
    LOCAL_ROOT="${BARMBINI_LOCAL_ROOT:-/d/Local Sites/barmbini/app/public}"
else
    LOCAL_ROOT="${BARMBINI_LOCAL_ROOT:-$HOME/Local Sites/barmbini/app/public}"
fi

LOCAL_WP_CONTENT="$LOCAL_ROOT/wp-content"
LOCAL_SQL="${LOCAL_ROOT}/../sql/local.sql"
LOCAL_SQL_BACKUP="${LOCAL_ROOT}/../sql/local-before-fetch.sql"

# --- Workspace (fuer Archiv-Ablage und Backups) ---
WORKSPACE="${BARMBINI_WORKSPACE:-$(cd "$(dirname "$0")" && pwd)}"
ARCHIVE_PATH="$WORKSPACE/barmbini-fetch.tar.gz"

# --- Server ---
TARGET="${BARMBINI_TARGET:-217.160.74.128}"
SITE_DOMAIN="${BARMBINI_SITE_DOMAIN:-barmbini.de}"
SERVER_IMPORT="/root/barmbini-import"
SERVER_WEBROOT="/var/www/barmbini"
SERVER_DB_FILE="/root/barmbini-db.txt"
LIVE_URL="https://$SITE_DOMAIN/kontakt/"
LOCAL_URL="https://barmbini.local/kontakt/"

# --- Farben (optional, werden nur bei Terminal-Support gesetzt) ---
if [ -t 1 ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    CYAN='\033[0;36m'
    GRAY='\033[0;90m'
    DARK_YELLOW='\033[0;33m'
    NC='\033[0m' # No Color
else
    RED='' GREEN='' YELLOW='' CYAN='' GRAY='' DARK_YELLOW='' NC=''
fi

# -------------------------------------------------------------------
# Flag-Parsing
# -------------------------------------------------------------------
FULL=false
NOBACKUP=false
NOBROWSER=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --full|-f)     FULL=true ;;
        --nobackup|-nb) NOBACKUP=true ;;
        --nobrowser|-n) NOBROWSER=true ;;
        --force|-ff)   FORCE=true ;;
        --help|-h)
            echo "Barmbini Fetch Tool – Server -> Local"
            echo ""
            echo "  --full, -f      Modus A: Vollabgleich mit DB + Uploads"
            echo "  --nobackup, -nb Ueberspringt lokales Backup"
            echo "  --nobrowser, -n Kein Browser-Tab nach Fetch"
            echo "  --force, -ff    Erzwingt DB-Import auch bei altem Dump"
            echo "  --help, -h      Diese Hilfe"
            echo ""
            echo "Umgebungsvariablen:"
            echo "  BARMBINI_LOCAL_ROOT  Pfad zur lokalen WP-Installation"
            echo "  BARMBINI_WORKSPACE   Pfad zum Arbeitsverzeichnis"
            echo "  BARMBINI_TARGET      Server-IP oder Hostname"
            exit 0
            ;;
        *) echo "Unbekannte Option: $arg (--help fuer Hilfe)"; exit 1 ;;
    esac
done

# Modus-Label
if $FULL; then
    MODE_LABEL="A (Vollabgleich + DB + Uploads)"
else
    MODE_LABEL="B (Nur Code, keine DB/Uploads)"
fi

# -------------------------------------------------------------------
# Hilfsfunktionen
# -------------------------------------------------------------------

# Fehler mit Meldung und Abbruch
die() {
    echo -e "${RED}FEHLER: $1${NC}" >&2
    exit 1
}

warn() {
    echo -e "${YELLOW}WARNUNG: $1${NC}" >&2
}

info() {
    echo -e "${GRAY}       $1${NC}"
}

ok() {
    echo -e "${GREEN}       OK${NC}"
}

# Browser oeffnen
open_browser() {
    local url="$1"
    case "$OS" in
        linux)   xdg-open "$url" 2>/dev/null || true ;;
        macos)   open "$url" 2>/dev/null || true ;;
        windows) cmd.exe /c start "$url" 2>/dev/null || true ;;
        *)       echo "Besuche: $url" ;;
    esac
}

# -------------------------------------------------------------------
# Header
# -------------------------------------------------------------------
echo ""
echo -e "${CYAN}================================================================${NC}"
echo -e "${CYAN}  Barmbini Fetch – Server -> Local${NC}"
echo -e "${CYAN}  Modus $MODE_LABEL${NC}"
echo -e "${CYAN}  Quelle: $TARGET${NC}"
echo -e "${CYAN}  Ziel:   $LOCAL_ROOT${NC}"
echo -e "${CYAN}================================================================${NC}"
echo ""

# ===================================================================
# Schritt 1: Server-Datenbank dumpen und abholen (nur Modus A)
# ===================================================================
if $FULL; then
    echo -e "${YELLOW}[1/6] Exportiere Datenbank vom Server ...${NC}"

    # DB-Name vom Server auslesen
    DB_NAME=$(ssh "root@$TARGET" "awk -F= '/^DB_NAME=/{print \$2}' $SERVER_DB_FILE" 2>/dev/null)
    if [ -z "$DB_NAME" ]; then
        die "Konnte DB-Name nicht vom Server auslesen ($SERVER_DB_FILE)"
    fi
    info "DB-Name: $DB_NAME"

    # Dump auf dem Server erstellen
    SERVER_DUMP="$SERVER_IMPORT/live-dump.sql"
    if ! ssh "root@$TARGET" "mariadb-dump '$DB_NAME' > '$SERVER_DUMP' && echo 'DUMP_OK'"; then
        die "Datenbank-Dump auf dem Server fehlgeschlagen."
    fi
    info "Dump erstellt: $SERVER_DUMP"

    # Dump per SCP abholen
    info "Hole Dump per SCP ..."
    if ! scp -O "root@${TARGET}:${SERVER_DUMP}" "$LOCAL_SQL"; then
        die "SCP-Download des Dumps fehlgeschlagen."
    fi

    SQL_SIZE=$(du -h "$LOCAL_SQL" | cut -f1)
    info "SQL-Dump lokal: $LOCAL_SQL ($SQL_SIZE)"

    # Dump auf dem Server loeschen
    ssh "root@$TARGET" "rm -f '$SERVER_DUMP'" 2>/dev/null || true

    ok
else
    echo -e "${YELLOW}[1/6] Datenbank-Export UEBERSPRUNGEN (Modus B)${NC}"
fi
echo ""

# ===================================================================
# Schritt 2: Server-wp-content als tar.gz abholen
# ===================================================================
echo -e "${YELLOW}[2/6] Erstelle Archiv vom Server-wp-content ...${NC}"

SERVER_ARCHIVE="$SERVER_IMPORT/fetch-content.tar.gz"

# tar-Script auf den Server legen und ausfuehren
TAR_SCRIPT=$(mktemp)

if $FULL; then
    # Modus A: komplettes wp-content
    cat > "$TAR_SCRIPT" << 'TAREOF'
#!/bin/bash
set -e
cd /var/www/barmbini
tar -czf /root/barmbini-import/fetch-content.tar.gz \
    wp-content/languages \
    wp-content/plugins \
    wp-content/themes \
    wp-content/uploads \
    wp-content/index.php
echo 'TAR_OK'
TAREOF
else
    # Modus B: nur Code (keine uploads)
    cat > "$TAR_SCRIPT" << 'TAREOF'
#!/bin/bash
set -e
cd /var/www/barmbini
tar -czf /root/barmbini-import/fetch-content.tar.gz \
    wp-content/languages \
    wp-content/plugins \
    wp-content/themes \
    wp-content/index.php
echo 'TAR_OK'
TAREOF
fi

scp -O "$TAR_SCRIPT" "root@${TARGET}:/root/fetch-tar.sh"
if ! ssh "root@$TARGET" 'bash /root/fetch-tar.sh && rm /root/fetch-tar.sh'; then
    rm -f "$TAR_SCRIPT"
    die "Archiv-Erstellung auf dem Server fehlgeschlagen."
fi
rm -f "$TAR_SCRIPT"

# Archiv per SCP abholen
info "Hole Archiv per SCP ..."
rm -f "$ARCHIVE_PATH"
if ! scp -O "root@${TARGET}:${SERVER_ARCHIVE}" "$ARCHIVE_PATH"; then
    die "SCP-Download des Archivs fehlgeschlagen."
fi

ARCHIVE_SIZE=$(du -h "$ARCHIVE_PATH" | cut -f1)
info "Archiv lokal: $ARCHIVE_PATH ($ARCHIVE_SIZE)"

# Archiv auf dem Server loeschen
ssh "root@$TARGET" "rm -f '$SERVER_ARCHIVE'" 2>/dev/null || true

ok
echo ""

# ===================================================================
# Schritt 3: Lokales Backup
# ===================================================================
if ! $NOBACKUP; then
    echo -e "${YELLOW}[3/6] Erstelle lokales Backup ...${NC}"

    BACKUP_DIR="$WORKSPACE/barmbini-backup-$(date +%F-%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    # wp-content sichern
    info "Sichere wp-content ..."
    BACKUP_ZIP="$BACKUP_DIR/wp-content-before-fetch.zip"
    if [ -d "$LOCAL_WP_CONTENT" ]; then
        cd "$(dirname "$LOCAL_WP_CONTENT")"
        zip -r -q "$BACKUP_ZIP" \
            wp-content/languages \
            wp-content/plugins \
            wp-content/themes \
            wp-content/uploads \
            wp-content/index.php 2>/dev/null || true
        info "wp-content gesichert: $BACKUP_ZIP"
    else
        info "(kein wp-content zum Sichern)"
    fi
    cd "$WORKSPACE"

    # Lokale DB sichern (falls vorhanden)
    if [ -f "$LOCAL_SQL" ]; then
        cp "$LOCAL_SQL" "$LOCAL_SQL_BACKUP"
        info "DB gesichert: $LOCAL_SQL_BACKUP"
    fi

    info "Backup-Verzeichnis: $BACKUP_DIR"

    # Nur die letzten 2 lokalen Backups behalten
    OLD_BACKUPS=$(ls -d "$WORKSPACE"/barmbini-backup-* 2>/dev/null | sort | head -n -2)
    if [ -n "$OLD_BACKUPS" ]; then
        echo "$OLD_BACKUPS" | xargs rm -rf
        info "Alte Backups entfernt"
    fi

    ok
else
    echo -e "${YELLOW}[3/6] Lokales Backup UEBERSPRUNGEN (--nobackup)${NC}"
fi
echo ""

# ===================================================================
# Schritt 4: Lokale Datenbank importieren (nur Modus A)
# ===================================================================
if $FULL; then
    echo -e "${YELLOW}[4/6] Importiere Datenbank lokal ...${NC}"

    if [ ! -f "$LOCAL_SQL" ]; then
        die "SQL-Dump nicht gefunden: $LOCAL_SQL"
    fi

    # Alterspruefung
    SQL_AGE_SECS=$(( $(date +%s) - $(stat -c %Y "$LOCAL_SQL" 2>/dev/null || stat -f %m "$LOCAL_SQL" 2>/dev/null || echo 0) ))
    SQL_HOURS=$(( SQL_AGE_SECS / 3600 ))

    if [ "$SQL_HOURS" -gt 24 ] && [ "$FORCE" = false ]; then
        warn "SQL-Dump ist ${SQL_HOURS}h alt! Maximal 24h empfohlen."
        warn "Nutze --force zum Erzwingen."
        echo ""
        echo -e "${YELLOW}[!] MOECHTEN SIE DEN IMPORT TROTZDEM DURCHFUEHREN?${NC}"
        echo -e "${YELLOW}    Die lokale Datenbank wird durch den Server-Dump ERSETZT.${NC}"
        echo -e "${YELLOW}    Druecke J zum Fortfahren, eine andere Taste zum Abbrechen.${NC}"
        read -r -n 1 key
        echo ""
        if [ "$key" != "J" ] && [ "$key" != "j" ]; then
            die "Import ABGEBROCHEN."
        fi
    fi

    # DB-Import via wp-cli
    info "Importiere $LOCAL_SQL ..."
    info "(dies kann je nach Datenbankgroesse einen Moment dauern)"

    if wp --path="$LOCAL_ROOT" db import "$LOCAL_SQL" 2>/dev/null; then
        info "DB-Import erfolgreich."
    else
        warn "wp-cli-Import fehlgeschlagen."
        warn "Falls Local by Flywheel verwendet wird:"
        warn "  - Oeffne Local > Sites > barmbini > Database > Import"
        warn "  - Waehle: $LOCAL_SQL"
        warn ""
        warn "ODER fuehre manuell aus:"
        warn "  wp --path=$LOCAL_ROOT db import $LOCAL_SQL"
    fi

    ok
else
    echo -e "${YELLOW}[4/6] Datenbank-Import UEBERSPRUNGEN (Modus B)${NC}"
fi
echo ""

# ===================================================================
# Schritt 5: Lokales wp-content entpacken
# ===================================================================
echo -e "${YELLOW}[5/6] Entpacke wp-content lokal ...${NC}"

if [ ! -f "$ARCHIVE_PATH" ]; then
    die "Archiv nicht gefunden: $ARCHIVE_PATH"
fi

# Alte wp-content-Ordner vor dem Entpacken loeschen
info "Loesche alte wp-content-Ordner ..."
FOLDERS="languages plugins themes index.php"
if $FULL; then
    FOLDERS="$FOLDERS uploads"
fi

for folder in $FOLDERS; do
    TARGET_PATH="$LOCAL_WP_CONTENT/$folder"
    if [ -e "$TARGET_PATH" ]; then
        rm -rf "$TARGET_PATH"
        info "Geloescht: $TARGET_PATH"
    fi
done

# Entpacken (--strip-components=1 entfernt den fuehrenden "wp-content/" Praefix)
info "Entpacke $ARCHIVE_PATH nach $LOCAL_WP_CONTENT ..."
mkdir -p "$LOCAL_WP_CONTENT"
if ! tar -xzf "$ARCHIVE_PATH" -C "$LOCAL_WP_CONTENT" --strip-components=1; then
    die "Entpacken fehlgeschlagen."
fi

ok
echo ""

# ===================================================================
# Schritt 5a: Sanity-Check
# ===================================================================
echo -e "${YELLOW}[5a] Sanity-Check: Theme + Plugin ...${NC}"

CHECK_OK=true

check_file() {
    local label="$1"
    local path="$2"
    if [ -e "$path" ]; then
        echo -e "${GREEN}       $label: OK${NC}"
    else
        echo -e "${RED}       $label: FEHLT!${NC}"
        CHECK_OK=false
    fi
}

check_file "Kadence-Theme"      "$LOCAL_WP_CONTENT/themes/kadence"
check_file "barmbini-core"     "$LOCAL_WP_CONTENT/plugins/barmbini-core/barmbini-core.php"
check_file "WordPress-Index"   "$LOCAL_ROOT/index.php"

if ! $CHECK_OK; then
    warn "Sanity-Check fehlgeschlagen! Fetch ist moeglicherweise unvollstaendig."
fi
echo ""

# ===================================================================
# Schritt 6: URL-Umschreibung und Cache leeren
# ===================================================================
echo -e "${YELLOW}[6/6] URL-Umschreibung + Cache leeren ...${NC}"

if $FULL; then
    # search-replace: Server-Domain -> barmbini.local
    info "Ersetze '$SITE_DOMAIN' -> 'barmbini.local' ..."
    if wp --path="$LOCAL_ROOT" search-replace "$SITE_DOMAIN" "barmbini.local" --all-tables --skip-columns=guid 2>/dev/null; then
        info "URL-Umschreibung erfolgreich."
    else
        warn "search-replace fehlgeschlagen. Bitte manuell ausfuehren:"
        warn "  wp --path=$LOCAL_ROOT search-replace '$SITE_DOMAIN' 'barmbini.local' --all-tables --skip-columns=guid"
    fi
fi

# Cache leeren
info "Leere Cache ..."
rm -rf "$LOCAL_WP_CONTENT/cache/"* 2>/dev/null || true
wp --path="$LOCAL_ROOT" cache flush 2>/dev/null || true

ok
echo ""

# ===================================================================
# Aufraeumen
# ===================================================================
rm -f "$ARCHIVE_PATH"
ssh "root@$TARGET" "rm -rf $SERVER_IMPORT/fetch-content.tar.gz $SERVER_IMPORT/live-dump.sql /root/fetch-tar.sh" 2>/dev/null || true

# ===================================================================
# Fertig
# ===================================================================
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}  Fetch abgeschlossen – Modus $MODE_LABEL${NC}"
echo -e "${GREEN}  Server -> Local${NC}"
echo -e "${GREEN}  Lokale URL: $LOCAL_URL${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""

if ! $NOBACKUP; then
    echo -e "${DARK_YELLOW}  Backup liegt in: $WORKSPACE/barmbini-backup-*${NC}"
    echo ""
fi

# ===================================================================
# Browser oeffnen
# ===================================================================
if ! $NOBROWSER; then
    open_browser "$LOCAL_URL"
fi
