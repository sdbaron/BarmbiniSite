#!/usr/bin/env bash
# =============================================================================
# Barmbini Deployment – Plattformunabhaengiges Skript (macOS, Linux, Windows/Git Bash)
#
# Verwendung:
#   ./deploy.sh                  # Modus B: Nur Code deployen
#   ./deploy.sh --full           # Modus A: Vollabgleich mit SQL
#   ./deploy.sh --full --force   # Modus A: mit frischem Dump (empfohlen!)
#   ./deploy.sh --nobackup       # Deployment ohne vorheriges Backup
#   ./deploy.sh --full --nobrowser # Modus A, kein Browser oeffnen
#
# Voraussetzungen:
#   - bash >= 4.0
#   - ssh, scp, zip, curl im PATH
#   - Lokale WordPress-Installation (Pfad in Konfiguration anpassbar)
#   - SSH-Schluessel zum Server hinterlegt
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
# Windows (Git Bash): /c/Users/...  oder  /d/Local Sites/...
# macOS (Local):      /Users/.../Local Sites/...
# Linux:              /home/.../...

if [ "$OS" = "windows" ]; then
    LOCAL_ROOT="${BARMBINI_LOCAL_ROOT:-/d/Local Sites/barmbini/app/public}"
else
    LOCAL_ROOT="${BARMBINI_LOCAL_ROOT:-$HOME/Local Sites/barmbini/app/public}"
fi

LOCAL_WP_CONTENT="$LOCAL_ROOT/wp-content"
LOCAL_SQL="${LOCAL_ROOT}/../sql/local.sql"

# --- Workspace (fuer ZIP-Ablage) ---
WORKSPACE="${BARMBINI_WORKSPACE:-$(cd "$(dirname "$0")" && pwd)}"
ZIP_PATH="$WORKSPACE/barmbini-deploy.zip"

# --- Server ---
TARGET="${BARMBINI_TARGET:-217.160.74.128}"
SERVER_IMPORT="/root/barmbini-import"
SERVER_WEBROOT="/var/www/barmbini"
LIVE_URL="http://$TARGET/kontakt/"
DUMP_URL="https://barmbini.local/dump-db.php"

# --- Farben (optional, werden nur bei Terminal-Support gesetzt) ---
if [ -t 1 ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    CYAN='\033[0;36m'
    GRAY='\033[0;90m'
    NC='\033[0m' # No Color
else
    RED='' GREEN='' YELLOW='' CYAN='' GRAY='' NC=''
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
            echo "Barmbini Deployment Tool"
            echo ""
            echo "  --full, -f      Modus A: Vollabgleich mit SQL-Import"
            echo "  --nobackup, -nb Ueberspringt Server-Backup"
            echo "  --nobrowser, -n Kein Browser-Tab nach Deployment"
            echo "  --force, -ff    Frischen SQL-Dump vor Deployment erstellen"
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
    MODE_LABEL="A (Vollabgleich + SQL)"
else
    MODE_LABEL="B (Nur Code, Live-Daten sicher)"
fi

# -------------------------------------------------------------------
# Hilfsfunktionen
# -------------------------------------------------------------------

# OS-abhaengiges stat fuer Dateialter in Sekunden
file_age_seconds() {
    local file="$1"
    case "$OS" in
        linux)   stat -c %Y "$file" 2>/dev/null || echo 0 ;;
        macos)   stat -f %m "$file" 2>/dev/null || echo 0 ;;
        windows) stat -c %Y "$file" 2>/dev/null || echo 0 ;;
        *)       stat -c %Y "$file" 2>/dev/null || echo 0 ;;
    esac
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

# -------------------------------------------------------------------
# Header
# -------------------------------------------------------------------
echo ""
echo -e "${CYAN}================================================================${NC}"
echo -e "${CYAN}  Barmbini Deployment – Modus $MODE_LABEL${NC}"
echo -e "${CYAN}  Ziel: $TARGET${NC}"
echo -e "${CYAN}================================================================${NC}"
echo ""

# ===================================================================
# Schritt 0: Frischen Dump erstellen (--force)
# ===================================================================
if $FORCE && $FULL; then
    echo -e "${YELLOW}[0/6] Erstelle frischen SQL-Dump von der lokalen DB ...${NC}"

    RESULT=$(curl -k -s -S --max-time 120 "$DUMP_URL" 2>&1) && CURL_EXIT=0 || CURL_EXIT=$?

    if [ $CURL_EXIT -ne 0 ] || [[ "$RESULT" != OK* ]]; then
        die "Dump fehlgeschlagen: $RESULT"
    fi
    info "$RESULT"
    echo ""
fi

# ===================================================================
# Schritt 1: Quellen pruefen
# ===================================================================
echo -e "${YELLOW}[1/6] Pruefe lokale Quellen ...${NC}"

if [ ! -d "$LOCAL_ROOT" ]; then
    die "Lokale WordPress-Installation nicht gefunden: $LOCAL_ROOT"
fi
info "WordPress-Root: $LOCAL_ROOT"

if [ ! -d "$LOCAL_WP_CONTENT" ]; then
    die "wp-content nicht gefunden: $LOCAL_WP_CONTENT"
fi

if $FULL; then
    if [ ! -f "$LOCAL_SQL" ]; then
        die "SQL-Dump nicht gefunden: $LOCAL_SQL"
    fi

    SQL_SIZE=$(du -h "$LOCAL_SQL" | cut -f1)
    SQL_AGE_SECS=$(( $(date +%s) - $(file_age_seconds "$LOCAL_SQL") ))
    SQL_HOURS=$(( SQL_AGE_SECS / 3600 ))
    SQL_MINS=$(( (SQL_AGE_SECS % 3600) / 60 ))

    if [ "$SQL_HOURS" -gt 24 ] && [ "$FORCE" = false ]; then
        die "SQL-Dump ist ${SQL_HOURS}h ${SQL_MINS}m alt! Maximal 24h erlaubt.\n  -> Bitte lokale DB exportieren nach: $LOCAL_SQL\n  -> Oder mit --force erzwingen."
    fi
    info "SQL-Dump: $LOCAL_SQL ($SQL_SIZE, Alter: ${SQL_HOURS}h ${SQL_MINS}m)"
fi
ok
echo ""

# ===================================================================
# Schritt 2: ZIP bauen
# ===================================================================
echo -e "${YELLOW}[2/6] Baue Deployment-Archiv ...${NC}"

rm -f "$ZIP_PATH"

# Ins wp-content-Verzeichnis wechseln, damit Pfade relativ bleiben
cd "$(dirname "$LOCAL_WP_CONTENT")"

if $FULL; then
    # Modus A: komplettes wp-content
    zip -r -q "$ZIP_PATH" \
        wp-content/languages \
        wp-content/plugins \
        wp-content/themes \
        wp-content/uploads \
        wp-content/index.php
else
    # Modus B: nur Code (keine uploads)
    zip -r -q "$ZIP_PATH" \
        wp-content/languages \
        wp-content/plugins \
        wp-content/themes \
        wp-content/index.php
fi

ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
info "Archiv: $ZIP_PATH ($ZIP_SIZE)"
ok
echo ""

cd "$WORKSPACE"

# ===================================================================
# Schritt 3: Server-Backup
# ===================================================================
if ! $NOBACKUP; then
    echo -e "${YELLOW}[3/6] Erstelle Server-Backup ...${NC}"

    BACKUP_SCRIPT=$(mktemp)
    cat > "$BACKUP_SCRIPT" << 'BACKUPEOF'
#!/bin/bash
set -e
BACKUP_DIR="/root/barmbini-backup-$(date +%F-%H%M%S)"
mkdir -p "$BACKUP_DIR"
wp --path=/var/www/barmbini db export "$BACKUP_DIR/live-before-deploy.sql" --allow-root
tar -czf "$BACKUP_DIR/wp-content-before-deploy.tar.gz" -C /var/www/barmbini wp-content
echo "$BACKUP_DIR"
BACKUPEOF

    scp -O "$BACKUP_SCRIPT" "root@${TARGET}:/root/backup.sh"
    if ssh "root@$TARGET" 'bash /root/backup.sh && rm /root/backup.sh'; then
        rm -f "$BACKUP_SCRIPT"
        :  # Backup erfolgreich
    else
        rm -f "$BACKUP_SCRIPT"
        warn "Backup fehlgeschlagen! Deployment wird abgebrochen."
        warn "Pruefe SSH-Verbindung oder fuehre mit --nobackup aus."
        exit 1
    fi
    ok
else
    echo -e "${YELLOW}[3/6] Server-Backup UEBERSPRUNGEN (--nobackup)${NC}"
fi
echo ""

# ===================================================================
# Schritt 4: SCP-Upload
# ===================================================================
echo -e "${YELLOW}[4/6] Uebertrage Archiv per SCP ...${NC}"

ssh "root@$TARGET" "mkdir -p $SERVER_IMPORT"
if scp -O "$ZIP_PATH" "root@${TARGET}:${SERVER_IMPORT}/deploy.zip"; then
    :  # Upload erfolgreich
else
    die "SCP-Upload fehlgeschlagen."
fi
ok
echo ""

# ===================================================================
# Schritt 5: Installation auf dem Server
# ===================================================================
echo -e "${YELLOW}[5/6] Installiere auf dem Server ...${NC}"

INSTALL_SCRIPT=$(mktemp)

if $FULL; then
    # Modus A: Vollabgleich
    cat > "$INSTALL_SCRIPT" << INSTALLEOF
#!/bin/bash
set -e
cd $SERVER_WEBROOT
rm -rf $SERVER_WEBROOT/wp-content/languages $SERVER_WEBROOT/wp-content/plugins $SERVER_WEBROOT/wp-content/themes $SERVER_WEBROOT/wp-content/uploads $SERVER_WEBROOT/wp-content/index.php
cd $SERVER_IMPORT
unzip -o deploy.zip -d $SERVER_WEBROOT/
chown -R www-data:www-data $SERVER_WEBROOT/wp-content/languages $SERVER_WEBROOT/wp-content/plugins $SERVER_WEBROOT/wp-content/themes $SERVER_WEBROOT/wp-content/uploads $SERVER_WEBROOT/wp-content/index.php 2>/dev/null || true
# Korrigiere Dateirechte: Windows-ZIP verliert Execute-Bits fuer Ordner (X=execute nur fuer Ordner)
chmod -R u+rwX,go+rX,go-w $SERVER_WEBROOT/wp-content/plugins $SERVER_WEBROOT/wp-content/themes 2>/dev/null || true
rm -rf $SERVER_WEBROOT/wp-content/__MACOSX 2>/dev/null || true
wp --path=$SERVER_WEBROOT db import $SERVER_IMPORT/local.sql --allow-root
wp --path=$SERVER_WEBROOT search-replace 'barmbini.local' '$TARGET' --all-tables --allow-root 2>/dev/null || true
echo 'DEPLOY_OK'
INSTALLEOF

    scp -O "$LOCAL_SQL" "root@${TARGET}:${SERVER_IMPORT}/local.sql"
    scp -O "$INSTALL_SCRIPT" "root@${TARGET}:/root/install.sh"
    ssh "root@$TARGET" 'bash /root/install.sh && rm /root/install.sh'
else
    # Modus B: nur Code
    cat > "$INSTALL_SCRIPT" << INSTALLEOF
#!/bin/bash
set -e
cd $SERVER_IMPORT
unzip -o deploy.zip -d $SERVER_WEBROOT/
chown -R www-data:www-data $SERVER_WEBROOT/wp-content/languages $SERVER_WEBROOT/wp-content/plugins $SERVER_WEBROOT/wp-content/themes $SERVER_WEBROOT/wp-content/index.php 2>/dev/null || true
# Korrigiere Dateirechte: Windows-ZIP verliert Execute-Bits fuer Ordner (X=execute nur fuer Ordner)
chmod -R u+rwX,go+rX,go-w $SERVER_WEBROOT/wp-content/plugins $SERVER_WEBROOT/wp-content/themes 2>/dev/null || true
rm -rf $SERVER_WEBROOT/wp-content/__MACOSX 2>/dev/null || true
echo 'DEPLOY_OK'
INSTALLEOF

    scp -O "$INSTALL_SCRIPT" "root@${TARGET}:/root/install.sh"
    ssh "root@$TARGET" 'bash /root/install.sh && rm /root/install.sh'
fi

rm -f "$INSTALL_SCRIPT"
echo ""

# ===================================================================
# Schritt 5a: Sanity-Check
# ===================================================================
echo -e "${YELLOW}[5a] Sanity-Check: aktives Theme + Plugin ...${NC}"

CHECK_OK=true

check_file() {
    local label="$1"
    local path="$2"
    if ssh "root@$TARGET" "test -e '$path'" 2>/dev/null; then
        echo -e "${GREEN}       $label: OK${NC}"
    else
        echo -e "${RED}       $label: FEHLT!${NC}"
        CHECK_OK=false
    fi
}

check_file "Kadence-Theme"      "$SERVER_WEBROOT/wp-content/themes/kadence"
check_file "barmbini-core"     "$SERVER_WEBROOT/wp-content/plugins/barmbini-core/barmbini-core.php"
check_file "WordPress-Index"   "$SERVER_WEBROOT/index.php"

if ! $CHECK_OK; then
    warn "Sanity-Check fehlgeschlagen! Deployment ist moeglicherweise unvollstaendig."
fi
echo ""

# ===================================================================
# Schritt 6: Cache leeren
# ===================================================================
echo -e "${YELLOW}[6/6] Leere Cache ...${NC}"

ssh "root@$TARGET" "rm -rf $SERVER_WEBROOT/wp-content/cache/* 2>/dev/null; wp --path=$SERVER_WEBROOT cache flush --allow-root 2>/dev/null; echo 'CACHE_OK'"
ok
echo ""

# ===================================================================
# Aufraeumen
# ===================================================================
rm -f "$ZIP_PATH"
ssh "root@$TARGET" "rm -f $SERVER_IMPORT/deploy.zip $SERVER_IMPORT/local.sql" 2>/dev/null || true

# ===================================================================
# Fertig
# ===================================================================
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}  Deployment abgeschlossen – Modus $MODE_LABEL${NC}"
echo -e "${GREEN}  Live-URL: $LIVE_URL${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""

# ===================================================================
# Browser oeffnen
# ===================================================================
if ! $NOBROWSER; then
    open_browser "$LIVE_URL"
fi
