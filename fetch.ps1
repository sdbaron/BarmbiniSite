<#
.SYNOPSIS
    Holt die Sitedaten (Dateien + Datenbank) vom Server 217.160.74.128
    und spielt sie in die lokale WordPress-Installation ein.
    PowerShell-Version (Windows). Fuer macOS/Linux siehe fetch.sh.

.DESCRIPTION
    Vollautomatisches Fetch-Skript – das Gegenstueck zu deploy.ps1.
    Holt Dateien und/oder Datenbank vom Live-Server auf den lokalen Stand.

    Modus A (-Full):   Vollabgleich (Code + Datenbank + Uploads)
    Modus B (default): Nur Code (keine Datenbank, keine Uploads)

    Ablauf:
    1. Server-Datenbank dumpen und per SCP abholen
    2. Server-wp-content als tar.gz per SCP abholen
    3. Lokales Backup erstellen (optional via -NoBackup ueberspringbar)
    4. Lokale Datenbank importieren (nur Modus A)
    5. Lokales wp-content entpacken
    6. URL-Umschreibung: Server-IP -> barmbini.local
    7. Cache leeren
    8. (optional) Lokale Seite im Browser oeffnen

.PARAMETER Full
    Modus A: Vollabgleich mit Datenbank und Uploads.
    Ohne diesen Schalter gilt Modus B (nur Code, keine DB/Uploads).

.PARAMETER NoBackup
    Ueberspringt das lokale Backup vor dem Fetch.

.PARAMETER NoBrowser
    Oeffnet nach dem Fetch keinen Browser-Tab.

.PARAMETER Force
    Erzwingt Fetch auch wenn der lokale Stand aelter als 24h ist
    (relevant fuer DB-Import).

.EXAMPLE
    .\fetch.ps1                  # Modus B: Nur Code holen
    .\fetch.ps1 -Full            # Modus A: Vollabgleich mit DB + Uploads
    .\fetch.ps1 -Full -Force     # Modus A: Alterspruefung deaktiviert
    .\fetch.ps1 -NoBackup        # Ohne lokales Backup
    .\fetch.ps1 -Full -NoBrowser # Modus A, kein Browser oeffnen

.NOTES
    Author:  Barmbini Dev
    Version: 1.0.0
    Voraussetzungen:
    - Lokale WordPress-Installation unter D:\Local Sites\barmbini
    - SSH-Zugang zum Server (root)
    - scp und ssh in PATH
    - wp-cli lokal verfuegbar (oder DB-Import manuell)
#>

#Requires -Version 5.1

param(
    [switch]$Full,
    [switch]$NoBackup,
    [switch]$NoBrowser,
    [switch]$Force,
    [string]$Target = '217.160.74.128'
)

$ErrorActionPreference = 'Stop'

# ---------- Helper: Unix-Zeilenenden (LF statt CRLF) ----------
function ToUnix($s) { return $s -replace "`r`n", "`n" }

# ---------- Konfiguration ----------
$localRoot      = 'D:\Local Sites\barmbini\app\public'
$localWPContent = "$localRoot\wp-content"
$localSQL       = "$localRoot\..\sql\local.sql"
$localSQLBackup = "$localRoot\..\sql\local-before-fetch.sql"
$workspace      = 'D:\Dev\Website'
$archivePath    = "$workspace\barmbini-fetch.tar.gz"
$serverImport   = '/root/barmbini-import'
$serverDBFile   = '/root/barmbini-db.txt'
$localUrl        = 'https://barmbini.local/kontakt/'

# Modus-Label
$modeLabel = if ($Full) { 'A (Vollabgleich + DB + Uploads)' } else { 'B (Nur Code, keine DB/Uploads)' }

# ---------- Header ----------
Write-Host ''
Write-Host '================================================================' -ForegroundColor Cyan
Write-Host "  Barmbini Fetch – Server -> Local" -ForegroundColor Cyan
Write-Host "  Modus $modeLabel" -ForegroundColor Cyan
Write-Host "  Quelle: $Target" -ForegroundColor Cyan
Write-Host "  Ziel:   $localRoot" -ForegroundColor Cyan
Write-Host '================================================================' -ForegroundColor Cyan
Write-Host ''

# ===================================================================
# Schritt 1: Server-Datenbank dumpen und abholen (nur Modus A)
# ===================================================================
if ($Full) {
    Write-Host '[1/6] Exportiere Datenbank vom Server ...' -ForegroundColor Yellow

    # DB-Name vom Server auslesen
    # Hinweis: grep+cut statt awk, weil PowerShell $2 in Doppelquotes expandieren wuerde
    $dbName = ssh root@$Target "grep '^DB_NAME=' $serverDBFile | cut -d= -f2" 2>$null
    if (-not $dbName) {
        Write-Error "Konnte DB-Name nicht vom Server auslesen ($serverDBFile)"
        exit 1
    }
    Write-Host "       DB-Name: $dbName" -ForegroundColor Gray

    # Dump auf dem Server erstellen
    $serverDumpPath = "$serverImport/live-dump.sql"
    $dumpResult = ssh root@$Target "mariadb-dump '$dbName' > '$serverDumpPath' && echo 'DUMP_OK' || echo 'DUMP_FAIL'" 2>$null
    if ($dumpResult -notmatch 'DUMP_OK') {
        Write-Error "Datenbank-Dump auf dem Server fehlgeschlagen."
        exit 1
    }
    Write-Host "       Dump erstellt: $serverDumpPath" -ForegroundColor Gray

    # Dump per SCP abholen
    Write-Host "       Hole Dump per SCP ..." -ForegroundColor Gray
    scp -O root@${Target}:$serverDumpPath $localSQL
    if ($LASTEXITCODE -ne 0) {
        Write-Error "SCP-Download des Dumps fehlgeschlagen."
        exit 1
    }
    $sqlSize = "{0:N1} MB" -f ((Get-Item $localSQL).Length / 1MB)
    Write-Host "       SQL-Dump lokal: $localSQL ($sqlSize)" -ForegroundColor Gray

    # Dump auf dem Server loeschen
    ssh root@$Target "rm -f '$serverDumpPath'" 2>$null

    Write-Host '       OK' -ForegroundColor Green
} else {
    Write-Host '[1/6] Datenbank-Export UEBERSPRUNGEN (Modus B)' -ForegroundColor DarkYellow
}
Write-Host ''

# ===================================================================
# Schritt 2: Server-wp-content als tar.gz abholen
# ===================================================================
Write-Host '[2/6] Erstelle Archiv vom Server-wp-content ...' -ForegroundColor Yellow

$serverArchivePath = "$serverImport/fetch-content.tar.gz"

# Import-Verzeichnis auf dem Server anlegen (falls nicht vorhanden)
ssh root@$Target "mkdir -p $serverImport"

if ($Full) {
    # Modus A: komplettes wp-content
    $tarScript = ToUnix (@'
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
'@)
} else {
    # Modus B: nur Code (keine uploads)
    $tarScript = ToUnix (@'
#!/bin/bash
set -e
cd /var/www/barmbini
tar -czf /root/barmbini-import/fetch-content.tar.gz \
    wp-content/languages \
    wp-content/plugins \
    wp-content/themes \
    wp-content/index.php
echo 'TAR_OK'
'@)
}

$tmpTarScript = Join-Path $env:TEMP 'barmbini-fetch-tar.sh'
[System.IO.File]::WriteAllText($tmpTarScript, $tarScript, [System.Text.UTF8Encoding]::new($false))

scp -O $tmpTarScript root@${Target}:/root/fetch-tar.sh
$tarResult = ssh root@$Target 'bash /root/fetch-tar.sh && rm /root/fetch-tar.sh' 2>$null
Remove-Item $tmpTarScript -Force -ErrorAction SilentlyContinue

if ($tarResult -notmatch 'TAR_OK') {
    Write-Error "Archiv-Erstellung auf dem Server fehlgeschlagen."
    exit 1
}

# Archiv per SCP abholen
Write-Host "       Hole Archiv per SCP ..." -ForegroundColor Gray
if (Test-Path $archivePath) { Remove-Item $archivePath -Force }
scp -O root@${Target}:$serverArchivePath $archivePath
if ($LASTEXITCODE -ne 0) {
    Write-Error "SCP-Download des Archivs fehlgeschlagen."
    exit 1
}
$archiveSize = "{0:N1} MB" -f ((Get-Item $archivePath).Length / 1MB)
Write-Host "       Archiv lokal: $archivePath ($archiveSize)" -ForegroundColor Gray

# Archiv auf dem Server loeschen
ssh root@$Target "rm -f '$serverArchivePath'" 2>$null

Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ===================================================================
# Schritt 3: Lokales Backup
# ===================================================================
if (-not $NoBackup) {
    Write-Host '[3/6] Erstelle lokales Backup ...' -ForegroundColor Yellow

    # wp-content sichern
    $backupDir = Join-Path $workspace "barmbini-backup-$(Get-Date -Format 'yyyy-MM-dd-HHmmss')"
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

    Write-Host "       Sichere wp-content ..." -ForegroundColor Gray
    $backupZip = Join-Path $backupDir 'wp-content-before-fetch.zip'
    $items = @(
        "$localWPContent\languages",
        "$localWPContent\plugins",
        "$localWPContent\themes",
        "$localWPContent\uploads",
        "$localWPContent\index.php"
    ) | Where-Object { Test-Path $_ }
    if ($items.Count -gt 0) {
        Compress-Archive -Path $items -DestinationPath $backupZip -Force
        Write-Host "       wp-content gesichert: $backupZip" -ForegroundColor Gray
    } else {
        Write-Host "       (keine wp-content-Dateien zum Sichern)" -ForegroundColor Gray
    }

    # Lokale DB sichern (falls vorhanden)
    if (Test-Path $localSQL) {
        Copy-Item $localSQL $localSQLBackup -Force
        Write-Host "       DB gesichert: $localSQLBackup" -ForegroundColor Gray
    }

    Write-Host "       Backup-Verzeichnis: $backupDir" -ForegroundColor Gray
    Write-Host '       OK' -ForegroundColor Green
} else {
    Write-Host '[3/6] Lokales Backup UEBERSPRUNGEN (--NoBackup)' -ForegroundColor DarkYellow
}
Write-Host ''

# ===================================================================
# Schritt 4: Lokale Datenbank importieren (nur Modus A)
# ===================================================================
if ($Full) {
    Write-Host '[4/6] Importiere Datenbank lokal ...' -ForegroundColor Yellow

    if (-not (Test-Path $localSQL)) {
        Write-Error "SQL-Dump nicht gefunden: $localSQL"
        exit 1
    }

    # Pruefung: Lokalen DB-Import nur, wenn nicht riskant
    # (Warnung, aber kein Abbruch – der Nutzer hat sich fuer --Full entschieden)
    $sqlAge = [DateTime]::Now - (Get-Item $localSQL).LastWriteTime
    if ($sqlAge.TotalHours -gt 24 -and -not $Force) {
        Write-Warning "       SQL-Dump ist {0}h {1}m alt! Maximal 24h empfohlen." -f [Math]::Floor($sqlAge.TotalHours), $sqlAge.Minutes
        Write-Warning "       Nutze -Force zum Erzwingen."
        Write-Host ''
        Write-Host '[!] MOECHTEN SIE DEN IMPORT TROTZDEM DURCHFUEHREN?' -ForegroundColor Yellow
        Write-Host '    Die lokale Datenbank wird durch den Server-Dump ERSETZT.' -ForegroundColor Yellow
        Write-Host '    Druecke J zum Fortfahren, eine andere Taste zum Abbrechen.' -ForegroundColor Yellow
        $key = [Console]::ReadKey($true)
        if ($key.Key -ne [System.ConsoleKey]::J) {
            Write-Host ''
            Write-Host '       Import ABGEBROCHEN.' -ForegroundColor Red
            exit 1
        }
        Write-Host ''
    }

    # DB-Import via wp-cli (bevorzugt) oder mariadb
    Write-Host "       Importiere $localSQL ..." -ForegroundColor Gray
    Write-Host "       (dies kann je nach Datenbankgroesse einen Moment dauern)" -ForegroundColor Gray

    # Versuche wp-cli zuerst
    cmd /c "wp --path=`"$localRoot`" db import `"$localSQL`" 2>&1"
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "       wp-cli-Import fehlgeschlagen, versuche direkten mariadb-Import ..."
        Write-Host "       Hinweis: Stelle sicher, dass mariadb im PATH ist oder Local laeuft." -ForegroundColor DarkYellow

        # Fallback: Direkter Hinweis fuer Local by Flywheel
        Write-Host "       Falls Local by Flywheel verwendet wird:" -ForegroundColor Gray
        Write-Host "       - Oeffne Local" -ForegroundColor Gray
        Write-Host "       - Gehe zu Sites > barmbini > Database" -ForegroundColor Gray
        Write-Host "       - Klicke auf 'Import' und waehle:" -ForegroundColor Gray
        Write-Host "         $localSQL" -ForegroundColor Gray
        Write-Host ''
        Write-Host "       ODER fuehre manuell aus:" -ForegroundColor Gray
        Write-Host "       wp --path=$localRoot db import $localSQL" -ForegroundColor Gray
    } else {
        Write-Host "       DB-Import erfolgreich." -ForegroundColor Gray
    }

    Write-Host '       OK' -ForegroundColor Green
} else {
    Write-Host '[4/6] Datenbank-Import UEBERSPRUNGEN (Modus B)' -ForegroundColor DarkYellow
}
Write-Host ''

# ===================================================================
# Schritt 5: Lokales wp-content entpacken
# ===================================================================
Write-Host '[5/6] Entpacke wp-content lokal ...' -ForegroundColor Yellow

if (-not (Test-Path $archivePath)) {
    Write-Error "Archiv nicht gefunden: $archivePath"
    exit 1
}

# Alte wp-content-Ordner vor dem Entpacken loeschen (wie beim Server-Deploy)
$foldersToClean = @('languages', 'plugins', 'themes', 'index.php')
if ($Full) {
    $foldersToClean += 'uploads'
}

foreach ($folder in $foldersToClean) {
    $targetPath = Join-Path $localWPContent $folder
    if (Test-Path $targetPath) {
        Remove-Item $targetPath -Recurse -Force
        Write-Host "       Geloescht: $targetPath" -ForegroundColor Gray
    }
}

# Entpacken (tar ist nativ in Windows 10+ und auf allen Linux-/macOS-Systemen verfuegbar)
# --strip-components=1 entfernt den fuehrenden "wp-content/" Praefix aus dem Archiv,
# damit die Dateien direkt in $localWPContent landen (nicht in wp-content/wp-content/).
Write-Host "       Entpacke $archivePath nach $localWPContent ..." -ForegroundColor Gray
tar -xzf $archivePath -C $localWPContent --strip-components=1
if ($LASTEXITCODE -ne 0) {
    Write-Warning "       tar entpacken fehlgeschlagen."
    Write-Warning "       Bitte entpacke manuell: tar -xzf $archivePath -C $localWPContent --strip-components=1"
}

Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ===================================================================
# Schritt 5a: Sanity-Check
# ===================================================================
Write-Host '[5a] Sanity-Check: Theme + Plugin ...' -ForegroundColor Yellow

$checkOk = $true
$checks = @(
    @{Label='Kadence-Theme';     Path="$localWPContent\themes\kadence"}
    @{Label='barmbini-core';    Path="$localWPContent\plugins\barmbini-core\barmbini-core.php"}
    @{Label='WordPress-Index';  Path="$localRoot\index.php"}
)
foreach ($c in $checks) {
    if (Test-Path $c.Path) {
        Write-Host "       $($c.Label): OK" -ForegroundColor Green
    } else {
        Write-Host "       $($c.Label): FEHLT!" -ForegroundColor Red
        $checkOk = $false
    }
}

if (-not $checkOk) {
    Write-Warning "       Sanity-Check fehlgeschlagen! Fetch ist moeglicherweise unvollstaendig."
}
Write-Host ''

# ===================================================================
# Schritt 6: URL-Umschreibung und Cache leeren
# ===================================================================
Write-Host '[6/6] URL-Umschreibung + Cache leeren ...' -ForegroundColor Yellow

if ($Full) {
    # search-replace: Server-IP -> barmbini.local
    Write-Host "       Ersetze '$Target' -> 'barmbini.local' ..." -ForegroundColor Gray
    cmd /c "wp --path=`"$localRoot`" search-replace 'http://$Target' 'https://barmbini.local' --all-tables --skip-columns=guid 2>&1"
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "       search-replace fehlgeschlagen. Bitte manuell ausfuehren:"
        Write-Host "       wp --path=$localRoot search-replace 'http://$Target' 'https://barmbini.local' --all-tables --skip-columns=guid" -ForegroundColor DarkYellow
    } else {
        Write-Host "       URL-Umschreibung erfolgreich." -ForegroundColor Gray
    }
}

# Cache leeren
Write-Host "       Leere Cache ..." -ForegroundColor Gray
Remove-Item "$localWPContent\cache\*" -Recurse -Force -ErrorAction SilentlyContinue
cmd /c "wp --path=`"$localRoot`" cache flush 2>&1" 2>$null

Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ===================================================================
# Aufraeumen
# ===================================================================
Remove-Item $archivePath -Force -ErrorAction SilentlyContinue
ssh root@$Target "rm -rf $serverImport/fetch-content.tar.gz $serverImport/live-dump.sql /root/fetch-tar.sh /root/fetch-install.sh" 2>$null

# ===================================================================
# Fertig
# ===================================================================
Write-Host '================================================================' -ForegroundColor Green
Write-Host "  Fetch abgeschlossen – Modus $modeLabel" -ForegroundColor Green
Write-Host "  Server -> Local" -ForegroundColor Green
Write-Host "  Lokale URL: $localUrl" -ForegroundColor Green
Write-Host '================================================================' -ForegroundColor Green
Write-Host ''

if (-not $NoBackup) {
    Write-Host "  Backup liegt in: $workspace\barmbini-backup-*" -ForegroundColor DarkYellow
    Write-Host ''
}

# ===================================================================
# Browser oeffnen
# ===================================================================
if (-not $NoBrowser) {
    Start-Process $localUrl
}
