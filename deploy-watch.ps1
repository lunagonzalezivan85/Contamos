<#
.SYNOPSIS
    Deploy automático: cada 2s busca archivos de app/ y public/ modificados
    desde que arrancó y los sube al server. Ctrl+C para detener.

.USO
    .\deploy-watch.ps1      → vigilancia encendida hasta cerrar la ventana
#>

# ================= CONFIG (la misma de deploy.ps1) =================
$FtpHost   = "ftp://contamos.softlutionic.com"
$FtpUser   = "actas_maria"
$FtpPass   = "easy2023"
$RemoteDir = "/"          # carpeta remota del proyecto
# ===================================================================

$root = $PSScriptRoot
$cred = "${FtpUser}:$FtpPass"

function Excluido([string]$rel) {
    $rel2 = $rel -replace '\\', '/'
    foreach ($x in @('writable/', 'public/uploads/', 'node_modules/', 'vendor/', '.git/', 'tests/')) {
        if ($rel2 -like "$x*") { return $true }
    }
    return $false
}

Write-Host "👁  Vigilando app/ y public/ — cada archivo que guardes se sube al server." -ForegroundColor Green
Write-Host "   Ctrl+C para detener.`n" -ForegroundColor DarkGray

$ultima = Get-Date
while ($true) {
    Start-Sleep -Seconds 2
    $ahora  = Get-Date
    $nuevos = Get-ChildItem (Join-Path $root 'app'), (Join-Path $root 'public') -Recurse -File |
        Where-Object { $_.LastWriteTime -gt $ultima } |
        Where-Object { -not (Excluido ($_.FullName.Substring($root.Length + 1))) }

    foreach ($f in $nuevos) {
        $rel = $f.FullName.Substring($root.Length + 1)
        $url = "$FtpHost$RemoteDir" + ($rel -replace '\\', '/')
        curl.exe -s -T "$($f.FullName)" --user $cred --ftp-create-dirs "$url" 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  ↑ $rel" -ForegroundColor DarkGray
        } else {
            Write-Host "  ✗ $rel — error al subir" -ForegroundColor Red
        }
    }
    $ultima = $ahora
}
