<#
.SYNOPSIS
    Sync barmbini-core plugin between local install and workspace.

.DESCRIPTION
    Bidirectional sync of the complete barmbini-core plugin:

    - Push (default):  Workspace --> Local install
                        (d:\Dev\Website --> D:\Local Sites\barmbini)
    - Pull (-Pull):    Local install --> Workspace
                        (D:\Local Sites\barmbini --> d:\Dev\Website)

.PARAMETER Pull
    Copy from local install into workspace (after testing in Local).

.PARAMETER NoBrowser
    Skip browser reload after sync.

.EXAMPLE
    .\sync.ps1                # Push: Workspace --> Local
    .\sync.ps1 -Pull          # Pull: Local --> Workspace
    .\sync.ps1 -NoBrowser     # Push without browser reload

.NOTES
    Author:  Barmbini Dev
    Version: 0.1.0
#>

#Requires -Version 5.1

param(
    [switch]$Pull,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'

# ---------- Paths ----------
$workspacePlugin = 'd:\Dev\Website\wp-content\plugins\barmbini-core'
$localPlugin     = 'D:\Local Sites\barmbini\app\public\wp-content\plugins\barmbini-core'

# ---------- Header ----------
if ($Pull) {
    $directionLabel = 'Local --> Workspace'
    $source         = $localPlugin
    $destination    = $workspacePlugin
}
else {
    $directionLabel = 'Workspace --> Local'
    $source         = $workspacePlugin
    $destination    = $localPlugin
}

Write-Host '==============================================' -ForegroundColor Cyan
Write-Host "  barmbini-core Sync: $directionLabel" -ForegroundColor Cyan
Write-Host '==============================================' -ForegroundColor Cyan
Write-Host ''
Write-Host "Quelle:  $source"  -ForegroundColor Gray
Write-Host "Ziel:    $destination" -ForegroundColor Gray
Write-Host ''

# ---------- Check ----------
if (-not (Test-Path $source)) {
    Write-Error "Quelle nicht gefunden: $source"
    exit 1
}

if (-not (Test-Path $destination)) {
    Write-Warning "Ziel existiert nicht - wird erstellt: $destination"
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
}

# ---------- File list (auto-discover) ----------
Push-Location $source
$files = @(
    (Get-ChildItem -Recurse -File -Include '*.php','*.css','*.js' | Resolve-Path -Relative) -replace '^\.\\', ''
)
Pop-Location

# ---------- Sync ----------
$copied  = 0
$skipped = 0
$errors  = 0

foreach ($relative in $files) {
    $src  = Join-Path $source $relative
    $dest = Join-Path $destination $relative

    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }

    if (-not (Test-Path $src)) {
        Write-Warning "FEHLT in Quelle: $relative"
        $errors++
        continue
    }

    $srcHash  = (Get-FileHash $src -Algorithm MD5).Hash
    $destHash = if (Test-Path $dest) { (Get-FileHash $dest -Algorithm MD5).Hash } else { $null }

    if ($srcHash -eq $destHash) {
        $skipped++
        continue
    }

    try {
        Copy-Item -Path $src -Destination $dest -Force
        Write-Host "  OK $relative" -ForegroundColor Green
        $copied++
    }
    catch {
        Write-Warning "  FAIL $relative - $_"
        $errors++
    }
}

# ---------- Summary ----------
Write-Host ''
Write-Host '---------------- SYNC RESULT ----------------' -ForegroundColor Cyan
Write-Host "  Copied:     $copied"  -ForegroundColor $(if ($copied -gt 0) { 'Yellow' } else { 'Gray' })
Write-Host "  Skipped:    $skipped" -ForegroundColor Gray
Write-Host "  Errors:     $errors"  -ForegroundColor $(if ($errors -gt 0) { 'Red' } else { 'Gray' })
Write-Host '----------------------------------------------' -ForegroundColor Cyan
Write-Host ''

# ---------- Browser reload (only on Push, because that updates local) ----------
if (-not $Pull -and -not $NoBrowser -and $copied -gt 0) {
    Write-Host 'Trying to reload browser ...' -ForegroundColor DarkYellow

    try {
        $shell   = New-Object -ComObject Shell.Application
        $windows = $shell.Windows()

        foreach ($w in $windows) {
            if ($w.LocationURL -match 'barmbini\.local') {
                $w.Refresh()
                Write-Host "  OK Tab reloaded: $($w.LocationURL)" -ForegroundColor Green
                break
            }
        }
    }
    catch {
        Write-Host '  (no open browser tab found)' -ForegroundColor Gray
    }
}

Write-Host ''
Write-Host 'Done. Open: https://barmbini.local/kontakt/' -ForegroundColor DarkYellow
