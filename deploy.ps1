<#
.SYNOPSIS
    Sube al server solo los archivos modificados desde la última publicación.
    Registra la última fecha en .deploy-stamp (se toca tras cada subida).

.USO
    .\deploy.ps1           → sube lo que cambió desde la última vez
    .\deploy.ps1 -Todo     → sube TODO el proyecto (primera vez)
    .\deploy.ps1 -Borrar "public/css/viejo.css"  → borra un archivo en el server
#>
param(
    [switch]$Todo,
    [string]$Borrar
)

# ================= CONFIG =================
# Las credenciales viven en deploy.config.ps1 (local, gitignored).
$cfg = Join-Path $PSScriptRoot 'deploy.config.ps1'
if (!(Test-Path $cfg)) {
    Write-Host "Falta deploy.config.ps1 - copia deploy.config.ejemplo.ps1 y completa las credenciales." -ForegroundColor Red
    exit 1
}
. $cfg
# ==========================================

$root   = $PSScriptRoot
$stamp  = Join-Path $root ".deploy-stamp"
$cred   = "${FtpUser}:$FtpPass"

# Carpetas/archivos que se publican
$dirsDeploy = @('app', 'public', 'preload.php', 'spark', 'composer.json', 'composer.lock', '.htaccess')
# Rutas locales que NO se publican aunque estén dentro de lo anterior
$excluir = @(
    'writable\', 'writable/',
    'public\uploads\', 'public/uploads/',   # logos/archivos subidos viven en el server
    'node_modules\', 'vendor\', '.git\', 'tests\',
    '.env', '.deploy-stamp', 'deploy.ps1', 'deploy-watch.ps1',
    '.deploy-list.txt'
)

function Excluido([string]$rel) {
    $rel2 = $rel -replace '\\', '/'
    foreach ($x in $excluir) {
        $x2 = $x -replace '\\', '/'
        if ($rel2 -like "$x2*" -or $rel2 -eq $x2) { return $true }
    }
    return $false
}

function Subir([string]$local, [string]$rel) {
    $relUrl = $rel -replace '\\', '/'
    $url    = "$FtpHost$RemoteDir$relUrl"
    # --ftp-create-dirs crea las carpetas remotas que falten
    # --ftp-skip-pasv-ip: el server esta detras de NAT y devuelve su IP interna
    # en el PASV — sin el flag la conexion de datos muere por timeout.
    $out = curl.exe -s -T "$local" --user $cred --ftp-create-dirs --ftp-skip-pasv-ip "$url" 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Host "  X $rel  ($out)" -ForegroundColor Red; return $false }
    Write-Host "  -> $rel" -ForegroundColor DarkGray
    return $true
}

# ---------------- borrar remoto ----------------
if ($Borrar) {
    $url = "$FtpHost$RemoteDir" + ($Borrar -replace '\\', '/')
    curl.exe -s --user $cred --ftp-skip-pasv-ip --quote "DELE $RemoteDir$($Borrar -replace '\\','/')" "$FtpHost/" 2>&1 | Out-Null
    Write-Host "Borrado remoto: $Borrar (si existia)" -ForegroundColor Yellow
    exit 0
}

# ---------------- recolectar archivos ----------------
$ultima = if (Test-Path $stamp) { (Get-Item $stamp).LastWriteTime } else { [DateTime]::MinValue }

$files = @()
foreach ($d in $dirsDeploy) {
    $p = Join-Path $root $d
    if (Test-Path $p -PathType Container) {
        $files += Get-ChildItem $p -Recurse -File | Where-Object {
            $rel = $_.FullName.Substring($root.Length + 1)
            -not (Excluido $rel) -and ($Todo -or $_.LastWriteTime -gt $ultima)
        }
    } elseif (Test-Path $p) {
        $fi = Get-Item $p
        $rel = $fi.FullName.Substring($root.Length + 1)
        if (-not (Excluido $rel) -and ($Todo -or $fi.LastWriteTime -gt $ultima)) { $files += $fi }
    }
}

if ($files.Count -eq 0) {
    Write-Host "Nada que subir - sin cambios desde el ultimo deploy." -ForegroundColor Green
    exit 0
}

Write-Host "Subiendo $($files.Count) archivo(s) a $FtpHost$RemoteDir ..." -ForegroundColor Cyan
$ok = 0; $fail = @()
foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Length + 1)
    if (Subir $f.FullName $rel) { $ok++ } else { $fail += $rel }
}

if ($fail.Count -eq 0) {
    New-Item -Path $stamp -ItemType File -Force | Out-Null   # toca el sello
    Write-Host "Deploy listo - $ok archivo(s) publicados." -ForegroundColor Green
    Write-Host "Si subiste migraciones (app/Database/Migrations), corre las migraciones o importa el SQL en el server." -ForegroundColor Yellow
} else {
    Write-Host "Termino con $($fail.Count) error(es):" -ForegroundColor Red
    $fail | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    Write-Host "El sello NO se actualizo - reintenta corregidos los errores." -ForegroundColor Yellow
}
