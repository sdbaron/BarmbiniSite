#!/usr/bin/env bash
# =============================================================================
# Barmbini Backup-Fetch – Server-Backups nach lokal holen
# Plattformunabhaengig (macOS, Linux, Windows/Git Bash)
#
# Verwendung:
#   ./fetch-backups.sh                   # holen + Server bereinigen (Standard)
#   ./fetch-backups.sh --keep-server     # nur holen, Server-Kopien behalten
#   ./fetch-backups.sh --include-forensic # + Offsite-Kopie der Malware-Backups
#
# Voraussetzungen:
#   - bash >= 4.0
#   - ssh, scp, tar im PATH
#   - SSH-Schluessel zum Server hinterlegt
#   - Lokale Backups enthalten personenbezogene DB-Daten:
#     NICHT in Git committen (Ordner server-backups/ ist in .gitignore)
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
# Farben (nur bei Terminal-Support)
# -------------------------------------------------------------------
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
# Konfiguration
# -------------------------------------------------------------------
WORKSPACE="${BARMBINI_WORKSPACE:-$(cd "$(dirname "$0")" && pwd)}"
TARGET="${BARMBINI_TARGET:-217.160.74.128}"
LOCAL_DIR="${BARMBINI_BACKUP_DIR:-$WORKSPACE/server-backups}"

# Glob-Muster der zu holenden Backup-Ordner
SERVER_BACKUP_GLOB='/root/barmbini-backup-*'
SERVER_DB_BACKUP_GLOB='/root/barmbini-db-backup-*'
SERVER_ARCHIV_GLOB='/root/deploy-backups-archiv/barmbini-backup-*'

# Forensik-Backups (nur Offsite-Kopie, NIE vom Server loeschen)
FORENSIC_GLOBS=(
    '/root/resolver-malware-backup-*'
    '/root/malware-cleanup-*'
    '/root/systemd-resolved.service.malicious-*'
    '/root/update_persistence_backup_2026-04-22-105221'
    '/root/root-crontab-backup-2026-04-22-110302'
)

# -------------------------------------------------------------------
# Flags
# -------------------------------------------------------------------
KEEP_SERVER=false
INCLUDE_FORENSIC=false

while [ $# -gt 0 ]; do
    case "$1" in
        --keep-server)       KEEP_SERVER=true ;;
        --include-forensic)  INCLUDE_FORENSIC=true ;;
        -h|--help)
            echo "Verwendung: ./fetch-backups.sh [--keep-server] [--include-forensic]"
            echo "  --keep-server       Server-Kopien NICHT loeschen"
            echo "  --include-forensic  Zusaetzlich Malware-/Forensik-Backups holen (keine Loeschung)"
            exit 0
            ;;
        *)
            echo -e "${RED}Unbekanntes Argument: $1${NC}" >&2
            exit 1
            ;;
    esac
    shift
done

# -------------------------------------------------------------------
# Helper
# -------------------------------------------------------------------
ssh_run() {
    ssh -o BatchMode=yes -o ConnectTimeout=10 "root@$TARGET" "$1" 2>/dev/null || true
}

get_backups() {
    ssh_run "ls -d $SERVER_BACKUP_GLOB $SERVER_DB_BACKUP_GLOB $SERVER_ARCHIV_GLOB 2>/dev/null" | grep -v '^\s*$' || true
}

fetch_backup() {
    local remote="$1"
    local name
    name=$(basename "$remote")
    local dest="$LOCAL_DIR/$name"
    mkdir -p "$dest"

    echo -e "  -> ${GRAY}$name${NC}"

    local files
    files=$(ssh_run "ls $remote/* 2>/dev/null")
    for f in $files; do
        local fname
        fname=$(basename "$f")
        echo -e "     scp: ${GRAY}$fname${NC}"
        scp -O "root@${TARGET}:$f" "$dest/" 2>/dev/null
    done
    return 0
}

# Liefert 0, wenn alles OK ist (sonst 1)
test_integrity() {
    local dir="$1"
    local ok=0
    local f
    for f in "$dir"/*; do
        [ -e "$f" ] || continue
        case "$f" in
            *.sql)
                if ! head -n 2 "$f" | grep -qE 'MariaDB|MySQL|--'; then
                    echo -e "  ${YELLOW}SQL-Integritaet fragwuerdig: $(basename "$f")${NC}"
                    ok=1
                fi
                ;;
            *.tar.gz|*.tgz)
                if command -v tar >/dev/null 2>&1; then
                    if ! tar -tzf "$f" >/dev/null 2>&1; then
                        echo -e "  ${YELLOW}tar.gz-Integritaet fehlgeschlagen: $(basename "$f")${NC}"
                        ok=1
                    fi
                else
                    echo -e "  ${YELLOW}tar nicht verfuegbar – nicht geprueft: $(basename "$f")${NC}"
                    ok=1
                fi
                ;;
        esac
    done
    return $ok
}

remove_remote() {
    ssh_run "rm -rf '$1' && echo REMOVED" >/dev/null
    echo -e "  ${GREEN}geloescht vom Server: $1${NC}"
}

# -------------------------------------------------------------------
# Hauptablauf
# -------------------------------------------------------------------
echo ""
echo -e "${CYAN}==============================================${NC}"
echo -e "${CYAN}  Backup-Fetch: Server -> Lokal${NC}"
echo -e "${CYAN}==============================================${NC}"
echo -e "Quelle: ${GRAY}$TARGET${NC}"
echo -e "Ziel:   ${GRAY}$LOCAL_DIR${NC}"
echo ""

mkdir -p "$LOCAL_DIR"

# 1) Normale Backups auflisten
echo -e "${YELLOW}[1/3] Server-Backups auflisten ...${NC}"
mapfile -t backups < <(get_backups)

if [ ${#backups[@]} -eq 0 ]; then
    echo -e "  ${YELLOW}Keine Backups auf dem Server gefunden.${NC}"
else
    echo -e "  Gefunden: ${#backups[@]} Backup-Ordner"
    for b in "${backups[@]}"; do
        echo -e "  * $b"
    done
fi

echo ""
echo -e "${YELLOW}[2/3] Downloads ...${NC}"
fetched=()
for b in "${backups[@]}"; do
    if fetch_backup "$b"; then
        fetched+=("$b")
    fi
done

# 3) Verifizieren + (optional) Server bereinigen
echo ""
echo -e "${YELLOW}[3/3] Verifikation ...${NC}"
all_ok=true
for b in "${fetched[@]}"; do
    name=$(basename "$b")
    if test_integrity "$LOCAL_DIR/$name"; then
        echo -e "  ${GREEN}OK: $name${NC}"
        if [ "$KEEP_SERVER" = false ]; then
            remove_remote "$b"
        fi
    else
        echo -e "  ${YELLOW}NICHT OK – Server-Kopie bleibt erhalten: $name${NC}"
        all_ok=false
    fi
done

# Forensik-Offsite-Kopien (optional, nie loeschen)
if [ "$INCLUDE_FORENSIC" = true ]; then
    echo ""
    echo -e "${YELLOW}[Option] Forensik-Backups (Offsite-Kopie, keine Loeschung) ...${NC}"
    for glob in "${FORENSIC_GLOBS[@]}"; do
        while IFS= read -r r; do
            [ -n "$r" ] || continue
            fetch_backup "$r" >/dev/null
            echo -e "  ${GRAY}(bleibt auf dem Server)${NC}"
        done < <(ssh_run "ls -d $glob 2>/dev/null" | grep -v '^\s*$' || true)
    done
fi

# Zusammenfassung
echo ""
echo -e "${CYAN}================ FERTIG ================${NC}"
echo -e "  Downloads: ${#fetched[@]}"
if [ "$KEEP_SERVER" = true ]; then
    echo -e "  Server-Bereinigung: DEAKTIVIERT"
else
    echo -e "  Server-Bereinigung: AKTIV (nach Verifikation)"
fi
if [ "$all_ok" = true ]; then
    echo -e "  ${GREEN}Status: OK${NC}"
else
    echo -e "  ${YELLOW}Status: Teilweise fehlgeschlagen – Server-Kopien wurden nicht geloescht.${NC}"
fi
echo -e "  Lokale Backups: ${GRAY}$LOCAL_DIR${NC}"
echo -e "${CYAN}========================================${NC}"
