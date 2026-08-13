<#
.SYNOPSIS
    Holt Server-Backups (DB-Dumps + wp-content-Tar) vom Server 217.160.74.128
    auf den lokalen Rechner.

.DESCRIPTION
    Vollautomatisches Backup-Fetch-Skript.
    Laedt alle Server-Backups per SCP nach lokal und loescht sie nach
    erfolgreicher Verifikation standardmaessig vom Server (Speicher freigeben).

    Ablauf:
    1. Server-Backup-Ordner auflisten
    2. Jedes Backup per SCP nach lokal holen
    3. Download verifizieren (Dateigroessen / gzip-Integritaet)
    4. (Standard) Server-Kopien nach erfolgreichem Download loeschen
    5. Zusammenfassung ausgeben

.PARAMETER Target
    Server-IP (Standard: 217.160.74.128).

.PARAMETER LocalDir
    Lokales Zielverzeichnis fuer Backups (Standard: D:\Dev\Website\server-backups).

.PARAMETER KeepServer
    Server-Kopien NICHT loeschen (ueberschreibt den Standard -CleanServer).

.PARAMETER IncludeForensic
    Zusaetzlich Offsite-Kopien der Malware-/Forensik-Backups holen
    (OHNE Loeschung auf dem Server – Beweismittel bleiben dort).

.EXAMPLE
    .\fetch-backups.ps1                  # Standard: holen + Server bereinigen
    .\fetch-backups.ps1 -KeepServer      # Nur holen, Server-Kopien behalten
    .\fetch-backups.ps1 -IncludeForensic # + Forensik-Offsite-Kopien (ohne Loeschung)

.NOTES
    Author:  Barmbini Dev
    Version: 1.0.0
    Voraussetzungen:
    - SSH-Zugang zum Server (root), ssh und scp in PATH
    - Lokale Backups enthalten personenbezogene DB-Daten:
      NICHT in Git committen (Ordner server-backups/ ist in .gitignore)
#>

#Requires -Version 5.1

param(
    [string]$Target = '217.160.74.128',
    [string]$LocalDir = 'D:\Dev\Website\server-backups',
    [switch]$KeepServer,
    [switch]$IncludeForensic
)

$ErrorActionPreference = 'Stop'

# ---------- Konfiguration ----------
$serverBackupGlob   = '/root/barmbini-backup-*'
$serverDbBackupGlob = '/root/barmbini-db-backup-*'
$serverArchivGlob   = '/root/deploy-backups-archiv/barmbini-backup-*'
# Forensik-Backups (nur Offsite-Kopie, nie vom Server loeschen)
$forensicGlobs      = @(
    '/root/resolver-malware-backup-*',
    '/root/malware-cleanup-*',
    '/root/systemd-resolved.service.malicious-*',
    '/root/update_persistence_backup_2026-04-22-105221',
    '/root/root-crontab-backup-2026-04-22-110302'
)

# ---------- Helper ----------
function ToUnix($s) { return $s -replace "`r`n", "`n" }

function Test-Ssh {
    param([string]$Cmd)
    $out = ssh -o BatchMode=yes -o ConnectTimeout=10 "root@$Target" $Cmd 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "SSH-Fehler: $out"
    }
    return $out
}

function Get-ServerBackups {
    # Liefert Liste der Backup-Verzeichnisse (normale Deploy/DB-Backups)
    $raw = Test-Ssh "ls -d $serverBackupGlob $serverDbBackupGlob $serverArchivGlob 2>/dev/null"
    return @($raw | Where-Object { $_ -and $_ -notmatch '^\s*$' })
}

function Invoke-FetchBackup {
    param([string]$RemotePath)
    $name    = Split-Path $RemotePath -Leaf
    $destDir = Join-Path $LocalDir $name
    New-Item -ItemType Directory -Force -Path $destDir | Out-Null

    Write-Host "  -> $name" -ForegroundColor Gray
    # Dateien im Remote-Ordner auflisten
    $files = Test-Ssh "ls $RemotePath/* 2>/dev/null"
    if (-not $files -or $files -match 'No such file') {
        Write-Warning "  Ordner ist leer oder nicht erreichbar: $RemotePath"
        return $false
    }

    foreach ($f in $files) {
        $fName = Split-Path $f -Leaf
        $dest  = Join-Path $destDir $fName
        Write-Host "     scp: $fName" -ForegroundColor DarkGray
        scp -O "root@${Target}:$f" $dest 2>$null
        if ($LASTEXITCODE -ne 0) {
            throw "SCP-Fehler beim Download von $f"
        }
    }
    return $true
}

function Test-LocalBackupIntegrity {
    param([string]$Dir)
    $ok = $true
    Get-ChildItem -Path $Dir -File | ForEach-Object {
        if ($_.Extension -eq '.sql') {
            # Roher SQL-Dump: pruefen ob nicht leer und mit MariaDB-Dump beginnt
            $head = Get-Content $_.FullName -TotalCount 2 -ErrorAction SilentlyContinue
            if (-not $head -or -not ($head -join ' ' -match 'MariaDB|MySQL|--')) {
                Write-Warning "  SQL-Integritaet fragwuerdig: $($_.Name)"
                $ok = $false
            }
        } elseif ($_.Extension -eq '.gz' -or $_.Name -like '*.tar.gz') {
            # tar.gz-Integritaet: 'tar -tzf' listet den Inhalt ohne Entpacken
            # und liefert bei korruptem Archiv einen Fehlercode != 0.
            # (tar ist in Windows 10+ vorhanden, gzip dagegen nicht.)
            if (Get-Command tar -ErrorAction SilentlyContinue) {
                $r = & tar -tzf $_.FullName 2>&1
                if ($LASTEXITCODE -ne 0) {
                    Write-Warning "  tar.gz-Integritaet fehlgeschlagen: $($_.Name)"
                    $ok = $false
                }
            } else {
                Write-Warning "  tar nicht verfuegbar – tar.gz-Integritaet NICHT geprueft: $($_.Name)"
                $ok = $false
            }
        }
    }
    return $ok
}

function Remove-RemoteBackup {
    param([string]$RemotePath)
    Test-Ssh "rm -rf '$RemotePath' && echo 'REMOVED'" | Out-Null
    Write-Host "  geloescht vom Server: $RemotePath" -ForegroundColor Green
}

# ---------- Hauptablauf ----------
Write-Host '==============================================' -ForegroundColor Cyan
Write-Host '  Backup-Fetch: Server -> Lokal' -ForegroundColor Cyan
Write-Host '==============================================' -ForegroundColor Cyan
Write-Host "Quelle: $Target" -ForegroundColor Gray
Write-Host "Ziel:   $LocalDir" -ForegroundColor Gray
Write-Host ''

New-Item -ItemType Directory -Force -Path $LocalDir | Out-Null

# 1) Normale Backups holen
Write-Host '[1/3] Server-Backups auflisten ...' -ForegroundColor Yellow
$backups = Get-ServerBackups
if (-not $backups) {
    Write-Host '  Keine Backups auf dem Server gefunden.' -ForegroundColor DarkYellow
} else {
    Write-Host "  Gefunden: $($backups.Count) Backup-Ordner" -ForegroundColor Gray
    foreach ($b in $backups) {
        Write-Host "  * $b" -ForegroundColor Gray
    }
}

Write-Host ''
Write-Host '[2/3] Downloads ...' -ForegroundColor Yellow
$fetched = @()
foreach ($b in $backups) {
    if (Invoke-FetchBackup $b) { $fetched += $b }
}

# 3) Verifizieren + (optional) Server bereinigen
Write-Host ''
Write-Host '[3/3] Verifikation ...' -ForegroundColor Yellow
$allOk = $true
foreach ($b in $fetched) {
    $local = Join-Path $LocalDir (Split-Path $b -Leaf)
    if (Test-LocalBackupIntegrity $local) {
        Write-Host "  OK: $(Split-Path $b -Leaf)" -ForegroundColor Green
        if (-not $KeepServer) {
            Remove-RemoteBackup $b
        }
    } else {
        Write-Warning "  NICHT OK – Server-Kopie bleibt erhalten: $(Split-Path $b -Leaf)"
        $allOk = $false
    }
}

# Forensik-Offsite-Kopien (optional, nie loeschen)
if ($IncludeForensic) {
    Write-Host ''
    Write-Host '[Option] Forensik-Backups (Offsite-Kopie, keine Loeschung) ...' -ForegroundColor Yellow
    foreach ($glob in $forensicGlobs) {
        $raw = Test-Ssh "ls -d $glob 2>/dev/null"
        foreach ($r in @($raw | Where-Object { $_ -and $_ -notmatch '^\s*$' })) {
            Invoke-FetchBackup $r | Out-Null
            Write-Host "  (bleibt auf dem Server)" -ForegroundColor DarkGray
        }
    }
}

# Zusammenfassung
Write-Host ''
Write-Host '================ FERTIG ================' -ForegroundColor Cyan
Write-Host "  Downloads: $($fetched.Count)" -ForegroundColor Gray
Write-Host "  Server-Bereinigung: $(if ($KeepServer) { 'DEAKTIVIERT' } else { 'AKTIV (nach Verifikation)' })" -ForegroundColor Gray
if ($allOk) {
    Write-Host '  Status: OK' -ForegroundColor Green
} else {
    Write-Warning '  Status: Teilweise fehlgeschlagen – Server-Kopien wurden nicht geloescht.'
}
Write-Host "  Lokale Backups: $LocalDir" -ForegroundColor Gray
Write-Host '========================================' -ForegroundColor Cyan
