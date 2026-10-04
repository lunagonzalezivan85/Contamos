<#
.SYNOPSIS
    Aplica al server los SQL pendientes de writable\migraciones_sql\ en orden.
    Registra lo aplicado en la tabla remota CT_deploy_sync - re-ejecutar no
    duplica nada.

    RUTINA: al cerrar una fase que incluya migracion PHP, generar el .sql
    equivalente en writable\migraciones_sql\AAAA-MM-DD_descripcion.sql y
    correr este script (o pedirselo a Devin y lo corro directo desde aqui).

.USO
    .\sync-bd.ps1          -> aplica los .sql pendientes
    .\sync-bd.ps1 -Ver     -> lista aplicados vs pendientes, sin tocar nada
#>
param([switch]$Ver)

# ================= CONFIG =================
# Las credenciales viven en deploy.config.ps1 (local, gitignored).
$cfg = Join-Path $PSScriptRoot 'deploy.config.ps1'
if (!(Test-Path $cfg)) {
    Write-Host "Falta deploy.config.ps1 - copia deploy.config.ejemplo.ps1 y completa las credenciales." -ForegroundColor Red
    exit 1
}
. $cfg
$Tabla  = "CT_deploy_sync"
# ===========================================

$mysql = "C:\xampp\mysql\bin\mysql.exe"
$dir   = Join-Path $PSScriptRoot 'writable\migraciones_sql'

function Sql([string]$q) {
    & $mysql -h $DbHost -u $DbUser "-p$DbPass" $DbName -N -B -e $q 2>$null
}

# Tabla de registro (se crea si no existe - idempotente)
Sql "CREATE TABLE IF NOT EXISTS $Tabla (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, archivo VARCHAR(160) NOT NULL UNIQUE, aplicado DATETIME NOT NULL) ENGINE=InnoDB" | Out-Null

$aplicados = @{}
Sql "SELECT archivo FROM $Tabla" | ForEach-Object { if ($_) { $aplicados[$_.Trim()] = $true } }

$pendientes = Get-ChildItem $dir -Filter '*.sql' -ErrorAction SilentlyContinue |
    Sort-Object Name | Where-Object { -not $aplicados.ContainsKey($_.Name) }

if ($Ver) {
    Write-Host "`n=== Aplicados en el server ===" -ForegroundColor Cyan
    $aplicados.Keys | Sort-Object | ForEach-Object { Write-Host "  [ok] $_" -ForegroundColor Green }
    Write-Host "`n=== Pendientes ===" -ForegroundColor Cyan
    if ($pendientes.Count -eq 0) { Write-Host "  (ninguno - todo al dia)" -ForegroundColor DarkGray }
    $pendientes | ForEach-Object { Write-Host "  [..] $($_.Name)" -ForegroundColor Yellow }
    Write-Host ""
    exit 0
}

if ($pendientes.Count -eq 0) {
    Write-Host "BD del server ya esta sincronizada - nada pendiente." -ForegroundColor Green
    exit 0
}

Write-Host "Aplicando $($pendientes.Count) script(s) al server..." -ForegroundColor Cyan
foreach ($f in $pendientes) {
    Get-Content $f.FullName -Raw | & $mysql -h $DbHost -u $DbUser "-p$DbPass" $DbName 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Sql "INSERT INTO $Tabla (archivo, aplicado) VALUES ('$($f.Name)', NOW())" | Out-Null
        Write-Host "  [ok] $($f.Name)" -ForegroundColor Green
    } else {
        Write-Host "  [x] $($f.Name) - ERROR, revisa el SQL y reintenta" -ForegroundColor Red
        exit 1   # detener: las migraciones dependen del orden
    }
}
Write-Host "Listo - server sincronizado." -ForegroundColor Green
