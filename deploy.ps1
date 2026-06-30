<#
.SYNOPSIS
    Deployt die Barmbini-Website vom lokalen Stand auf den Server 217.160.74.128.

.DESCRIPTION
    Vollautomatisches Deployment-Skript. Fuehrt alle Schritte aus:

    Modus A (-Full):   Kompletter Abgleich (Code + Datenbank per SQL-Import)
    Modus B (default): Nur Code (keine Live-Daten anfassen)

    Ablauf:
    1. Lokale Quelle einlesen (erwartet D:\Local Sites\barmbini)
    2. ZIP-Archiv bauen (Code-only oder voll)
    3. Server-Backup erstellen (optional via -NoBackup ueberspringbar)
    4. Archiv per SCP uebertragen
    5. Auf dem Server entpacken und Rechte setzen
    6. WP-Cache leeren
    7. (optional) Live-Seite im Browser oeffnen

.PARAMETER Full
    Modus A: Vollabgleich mit SQL-Import (Datenbank wird ersetzt).
    Ohne diesen Schalter gilt Modus B (nur Code, keine Datenbank).

.PARAMETER NoBackup
    Ueberspringt das Server-Backup vor dem Deployment.

.PARAMETER NoBrowser
    Oeffnet nach dem Deployment keinen Browser-Tab.

.PARAMETER Target
    Server-Adresse. Default: 217.160.74.128

.EXAMPLE
    .\deploy.ps1                  # Modus B: Nur Code deployen
    .\deploy.ps1 -Full            # Modus A: Vollabgleich mit SQL
    .\deploy.ps1 -NoBackup        # Deployment ohne vorheriges Backup
    .\deploy.ps1 -Full -NoBrowser # Modus A, kein Browser oeffnen

.NOTES
    Author:  Barmbini Dev
    Version: 1.0.0
    Voraussetzungen:
    - Lokale WordPress-Installation unter D:\Local Sites\barmbini
    - SSH-Zugang zum Server (root)
    - scp und ssh in PATH
#>

#Requires -Version 5.1

param(
    [switch]$Full,
    [switch]$NoBackup,
    [switch]$NoBrowser,
    [string]$Target = '217.160.74.128'
)

$ErrorActionPreference = 'Stop'

# ---------- Konfiguration ----------
$localRoot      = 'D:\Local Sites\barmbini\app\public'
$localWPContent = "$localRoot\wp-content"
$localSQL       = "$localRoot\..\sql\local.sql"
$workspace      = 'D:\Dev\Website'
$zipPath        = "$workspace\barmbini-deploy.zip"
$serverImport   = '/root/barmbini-import'
$serverWebroot  = '/var/www/barmbini'
$serverDBFile   = '/root/barmbini-db.txt'
$liveUrl        = "http://$Target/kontakt/"

# Modus-Label
$modeLabel = if ($Full) { 'A (Vollabgleich + SQL)' } else { 'B (Nur Code, Live-Daten sicher)' }

# ---------- Header ----------
Write-Host ''
Write-Host '================================================================' -ForegroundColor Cyan
Write-Host "  Barmbini Deployment – Modus $modeLabel" -ForegroundColor Cyan
Write-Host "  Ziel: $Target" -ForegroundColor Cyan
Write-Host '================================================================' -ForegroundColor Cyan
Write-Host ''

# ---------- 1. Quellen pruefen ----------
Write-Host '[1/6] Pruefe lokale Quellen ...' -ForegroundColor Yellow

if (-not (Test-Path $localRoot)) {
    Write-Error "Lokale WordPress-Installation nicht gefunden: $localRoot"
    exit 1
}
Write-Host "       WordPress-Root: $localRoot" -ForegroundColor Gray

if (-not (Test-Path $localWPContent)) {
    Write-Error "wp-content nicht gefunden: $localWPContent"
    exit 1
}

if ($Full) {
    if (-not (Test-Path $localSQL)) {
        Write-Error "SQL-Dump nicht gefunden: $localSQL"
        Write-Host "       Tipp: Exportiere die lokale DB zuerst nach $localSQL" -ForegroundColor DarkYellow
        exit 1
    }
    $sqlSize = "{0:N1} MB" -f ((Get-Item $localSQL).Length / 1MB)
    Write-Host "       SQL-Dump: $localSQL ($sqlSize)" -ForegroundColor Gray
}
Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ---------- 2. ZIP bauen ----------
Write-Host '[2/6] Baue Deployment-Archiv ...' -ForegroundColor Yellow

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

if ($Full) {
    # Modus A: komplettes wp-content
    $zipItems = @(
        "$localWPContent\languages",
        "$localWPContent\plugins",
        "$localWPContent\themes",
        "$localWPContent\uploads",
        "$localWPContent\index.php"
    )
} else {
    # Modus B: nur Code (keine uploads, kein cache)
    $zipItems = @(
        "$localWPContent\languages",
        "$localWPContent\plugins",
        "$localWPContent\themes",
        "$localWPContent\index.php"
    )
}

Compress-Archive -Path $zipItems -DestinationPath $zipPath -Force
$zipSize = "{0:N1} MB" -f ((Get-Item $zipPath).Length / 1MB)
Write-Host "       Archiv: $zipPath ($zipSize)" -ForegroundColor Gray
Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ---------- 3. Server-Backup ----------
if (-not $NoBackup) {
    Write-Host '[3/6] Erstelle Server-Backup ...' -ForegroundColor Yellow

    $backupScript = @'
#!/bin/bash
set -e
BACKUP_DIR="/root/barmbini-backup-$(date +%F-%H%M%S)"
mkdir -p "$BACKUP_DIR"
DB_NAME=$(awk -F= '"'"'/^DB_NAME=/{print $2}'"'"' /root/barmbini-db.txt)
mariadb-dump "$DB_NAME" > "$BACKUP_DIR/live-before-deploy.sql"
tar -czf "$BACKUP_DIR/wp-content-before-deploy.tar.gz" -C /var/www/barmbini wp-content
echo "$BACKUP_DIR"
'@
    $backupScript | ssh root@$Target 'cat > /root/backup.sh && bash /root/backup.sh && rm /root/backup.sh'
    
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "       Backup fehlgeschlagen! Deployment wird abgebrochen."
        Write-Warning "       Pruefe SSH-Verbindung oder fuehre mit -NoBackup aus."
        exit 1
    }
    Write-Host '       OK' -ForegroundColor Green
} else {
    Write-Host '[3/6] Server-Backup UEBERSPRUNGEN (--NoBackup)' -ForegroundColor DarkYellow
}
Write-Host ''

# ---------- 4. SCP-Upload ----------
Write-Host '[4/6] Uebertrage Archiv per SCP ...' -ForegroundColor Yellow

ssh root@$Target "mkdir -p $serverImport"
scp -O $zipPath root@${Target}:$serverImport/deploy.zip

if ($LASTEXITCODE -ne 0) {
    Write-Error "SCP-Upload fehlgeschlagen."
    exit 1
}
Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ---------- 5. Entpacken und Rechte setzen ----------
Write-Host '[5/6] Installiere auf dem Server ...' -ForegroundColor Yellow

if ($Full) {
    # Modus A: volles wp-content ersetzen + SQL import + URL-Update
    $installScript = @"
#!/bin/bash
set -e
cd $serverWebroot
rm -rf wp-content/languages wp-content/plugins wp-content/themes wp-content/uploads wp-content/index.php
cd $serverImport
unzip -o deploy.zip -d $serverWebroot/wp-content/
chown -R www-data:www-data $serverWebroot/wp-content/languages $serverWebroot/wp-content/plugins $serverWebroot/wp-content/themes $serverWebroot/wp-content/uploads $serverWebroot/wp-content/index.php 2>/dev/null || true
rm -rf $serverWebroot/wp-content/__MACOSX 2>/dev/null || true
# SQL import
DB_NAME=\$(awk -F= '/^DB_NAME=/{print \$2}' $serverDBFile)
mariadb "\$DB_NAME" < /root/barmbini-import/local.sql
# URL ersetzen
wp --path=$serverWebroot search-replace 'barmbini.local' '$Target' --all-tables --allow-root 2>/dev/null || true
echo 'DEPLOY_OK'
"@
    # Sende lokalen SQL-Dump zusaetzlich
    scp -O $localSQL root@${Target}:/root/barmbini-import/local.sql
    $installScript | ssh root@$Target 'cat > /root/install.sh && bash /root/install.sh && rm /root/install.sh'
} else {
    # Modus B: nur Code ersetzen
    $installScript = @"
#!/bin/bash
set -e
cd $serverImport
unzip -o deploy.zip -d $serverWebroot/wp-content/
chown -R www-data:www-data $serverWebroot/wp-content/languages $serverWebroot/wp-content/plugins $serverWebroot/wp-content/themes $serverWebroot/wp-content/index.php 2>/dev/null || true
rm -rf $serverWebroot/wp-content/__MACOSX 2>/dev/null || true
echo 'DEPLOY_OK'
"@
    $installScript | ssh root@$Target 'cat > /root/install.sh && bash /root/install.sh && rm /root/install.sh'
}

if ($LASTEXITCODE -ne 0) {
    Write-Error "Deployment auf dem Server fehlgeschlagen."
    exit 1
}
Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ---------- 6. Cache leeren ----------
Write-Host '[6/6] Leere Cache ...' -ForegroundColor Yellow

ssh root@$Target "rm -rf $serverWebroot/wp-content/cache/* 2>/dev/null; wp --path=$serverWebroot cache flush --allow-root 2>/dev/null; echo 'CACHE_OK'"

Write-Host '       OK' -ForegroundColor Green
Write-Host ''

# ---------- Aufraeumen ----------
Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
ssh root@$Target "rm -f $serverImport/deploy.zip $serverImport/local.sql" 2>$null

# ---------- Fertig ----------
Write-Host '================================================================' -ForegroundColor Green
Write-Host "  Deployment abgeschlossen – Modus $modeLabel" -ForegroundColor Green
Write-Host "  Live-URL: $liveUrl" -ForegroundColor Green
Write-Host '================================================================' -ForegroundColor Green
Write-Host ''

# ---------- Browser oeffnen ----------
if (-not $NoBrowser) {
    Start-Process $liveUrl
}
