#!/usr/bin/env bash
# =============================================================================
# Sync barmbini-core plugin between local install and workspace.
#
# DESCRIPTION
#   Bidirectional sync of the complete barmbini-core plugin:
#
#   Push (default):  Workspace --> Local install
#   Pull (--pull):   Local install --> Workspace
#
# USAGE
#   ./sync.sh                  # Push: Workspace --> Local
#   ./sync.sh --pull           # Pull: Local --> Workspace
#   ./sync.sh --no-browser     # Push without browser reload
#
# AUTHOR
#   Barmbini Dev
#
# VERSION
#   0.1.0
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
# Argument parsing
# -------------------------------------------------------------------
PULL=false
NO_BROWSER=false

for arg in "$@"; do
    case "$arg" in
        --pull|-p) PULL=true ;;
        --no-browser|-n) NO_BROWSER=true ;;
        --help|-h)
            echo "Usage: ./sync.sh [--pull] [--no-browser]"
            echo ""
            echo "  --pull, -p       Copy from local install into workspace"
            echo "  --no-browser, -n Skip browser reload after sync"
            echo "  --help, -h       Show this help"
            exit 0
            ;;
        *)
            echo "Unknown argument: $arg"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
done

# -------------------------------------------------------------------
# Paths – anpassbar via Umgebungsvariablen
# -------------------------------------------------------------------
if [ "$OS" = "windows" ]; then
    WORKSPACE_PLUGIN="${BARMBINI_WORKSPACE_PLUGIN:-d:/Dev/Website/wp-content/plugins/barmbini-core}"
    LOCAL_PLUGIN="${BARMBINI_LOCAL_PLUGIN:-D:/Local Sites/barmbini/app/public/wp-content/plugins/barmbini-core}"
else
    WORKSPACE_PLUGIN="${BARMBINI_WORKSPACE_PLUGIN:-$HOME/work/barmbini/BarmbiniSite/wp-content/plugins/barmbini-core}"
    LOCAL_PLUGIN="${BARMBINI_LOCAL_PLUGIN:-$HOME/Local Sites/barmbini/app/public/wp-content/plugins/barmbini-core}"
fi

# -------------------------------------------------------------------
# MD5-Befehl je nach OS
# -------------------------------------------------------------------
get_md5() {
    local file="$1"
    if [ "$OS" = "macos" ]; then
        md5 -q "$file"
    else
        md5sum "$file" | cut -d' ' -f1
    fi
}

# -------------------------------------------------------------------
# Header
# -------------------------------------------------------------------
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
GRAY='\033[0;90m'
DARK_YELLOW='\033[0;33m'
NC='\033[0m' # No Color

if $PULL; then
    DIRECTION_LABEL='Local --> Workspace'
    SOURCE="$LOCAL_PLUGIN"
    DESTINATION="$WORKSPACE_PLUGIN"
else
    DIRECTION_LABEL='Workspace --> Local'
    SOURCE="$WORKSPACE_PLUGIN"
    DESTINATION="$LOCAL_PLUGIN"
fi

echo -e "${CYAN}=============================================="
echo -e "  barmbini-core Sync: $DIRECTION_LABEL"
echo -e "==============================================${NC}"
echo ""
echo -e "${GRAY}Quelle:  $SOURCE${NC}"
echo -e "${GRAY}Ziel:    $DESTINATION${NC}"
echo ""

# -------------------------------------------------------------------
# Check
# -------------------------------------------------------------------
if [ ! -d "$SOURCE" ]; then
    echo -e "${RED}ERROR: Quelle nicht gefunden: $SOURCE${NC}"
    exit 1
fi

if [ ! -d "$DESTINATION" ]; then
    echo -e "${YELLOW}WARNING: Ziel existiert nicht - wird erstellt: $DESTINATION${NC}"
    mkdir -p "$DESTINATION"
fi

# -------------------------------------------------------------------
# File list (auto-discover: .php, .css, .js)
# -------------------------------------------------------------------
files=()
while IFS= read -r -d '' file; do
    # Convert to relative path from SOURCE
    rel="${file#$SOURCE/}"
    files+=("$rel")
done < <(find "$SOURCE" -type f \( -name '*.php' -o -name '*.css' -o -name '*.js' \) -print0)

# -------------------------------------------------------------------
# Sync
# -------------------------------------------------------------------
COPIED=0
SKIPPED=0
ERRORS=0

for relative in "${files[@]}"; do
    src="$SOURCE/$relative"
    dest="$DESTINATION/$relative"

    dest_dir="$(dirname "$dest")"
    if [ ! -d "$dest_dir" ]; then
        mkdir -p "$dest_dir"
    fi

    if [ ! -f "$src" ]; then
        echo -e "${YELLOW}WARNING: FEHLT in Quelle: $relative${NC}"
        ((ERRORS++)) || true
        continue
    fi

    src_hash="$(get_md5 "$src")"
    dest_hash=""
    if [ -f "$dest" ]; then
        dest_hash="$(get_md5 "$dest")"
    fi

    if [ "$src_hash" = "$dest_hash" ]; then
        ((SKIPPED++)) || true
        continue
    fi

    if cp "$src" "$dest"; then
        echo -e "  ${GREEN}OK $relative${NC}"
        ((COPIED++)) || true
    else
        echo -e "  ${RED}FAIL $relative${NC}"
        ((ERRORS++)) || true
    fi
done

# -------------------------------------------------------------------
# Summary
# -------------------------------------------------------------------
echo ""
echo -e "${CYAN}---------------- SYNC RESULT ----------------"
echo -e "  Copied:     $COPIED"
echo -e "  Skipped:    $SKIPPED"
echo -e "  Errors:     $ERRORS"
echo -e "----------------------------------------------${NC}"
echo ""

# -------------------------------------------------------------------
# Browser reload (only on Push, because that updates local)
# -------------------------------------------------------------------
if ! $PULL && ! $NO_BROWSER && [ "$COPIED" -gt 0 ]; then
    echo -e "${DARK_YELLOW}Trying to reload browser ...${NC}"

    URL="https://barmbini.local/kontakt/"

    case "$OS" in
        macos)
            # AppleScript to reload the active tab matching the URL in Safari
            # Falls back to opening the URL if no matching tab is found
            osascript -e "
                tell application \"System Events\"
                    set browserList to {\"Safari\", \"Google Chrome\", \"Firefox\"}
                    repeat with browserName in browserList
                        if exists (processes where name is browserName) then
                            tell application browserName to activate
                            if browserName is \"Safari\" then
                                tell application \"Safari\"
                                    repeat with w in windows
                                        repeat with t in tabs of w
                                            if URL of t contains \"barmbini.local\" then
                                                set URL of t to URL of t
                                                return
                                            end if
                                        end repeat
                                    end repeat
                                end tell
                            else if browserName is \"Google Chrome\" then
                                tell application \"Google Chrome\"
                                    repeat with w in windows
                                        repeat with t in tabs of w
                                            if URL of t contains \"barmbini.local\" then
                                                reload t
                                                return
                                            end if
                                        end repeat
                                    end repeat
                                end tell
                            end if
                        end if
                    end repeat
                end tell
                # Fallback: just open the URL
                do shell script \"open $URL\"
            " 2>/dev/null || open "$URL"
            ;;
        linux)
            # Try to reload via xdotool, fallback to xdg-open
            if command -v xdotool &>/dev/null; then
                # Search for a browser window with barmbini in the title and send Ctrl+R
                window_id=$(xdotool search --name "barmbini" 2>/dev/null | head -1)
                if [ -n "$window_id" ]; then
                    xdotool windowactivate "$window_id" key ctrl+r 2>/dev/null || xdg-open "$URL"
                else
                    xdg-open "$URL"
                fi
            else
                xdg-open "$URL"
            fi
            ;;
        windows)
            # Git Bash / WSL: use cmd to open the URL
            cmd.exe /c start "$URL" 2>/dev/null || echo -e "${GRAY}  (could not open browser)${NC}"
            ;;
    esac

    echo ""
fi

echo -e "${DARK_YELLOW}Done. Open: https://barmbini.local/kontakt/${NC}"
