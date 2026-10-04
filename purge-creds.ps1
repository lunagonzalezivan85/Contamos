<#
.SYNOPSIS
    Purga las credenciales del HISTORIAL de git.

    Reescribe todos los commits reemplazando cada secreto listado en
    .purge-secrets.txt por ***REMOVED*** (git filter-branch + sed — viene
    con Git for Windows, no necesita Python ni BFG).

.CUANDO CORRERLO
    Solo DESPUES de que el colaborador haya hecho pull/clonado el repo y
    copiado las credenciales a su deploy.config.ps1 local. El purge borra
    las claves de cada commit (incluido GALVIN.md y los .ps1 viejos) y
    luego se hace force-push.

.USO
    1. Verificar .purge-secrets.txt (un valor por linea — ya gitignored).
    2. .\purge-creds.ps1          -> reescribe el historial local
       .\purge-creds.ps1 -Push    -> ademas git push --force origin main
    3. Avisar al equipo: cada clone viejo debe re-clonarse o correr
       git fetch ; git reset --hard origin/main

.NOTAS
    - Crea una rama local backup-pre-purge por seguridad.
    - Las credenciales de trabajo quedan en deploy.config.ps1 (local).
    - El repo queda limpio pero el historial anterior a este commit en
      clones ajenos conserva los valores (por eso se corre tras el pull).
#>
param([switch]$Push)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
Set-Location $root

$secretsFile = Join-Path $root '.purge-secrets.txt'
if (!(Test-Path $secretsFile)) {
    Write-Host "Falta .purge-secrets.txt - crea el archivo con un secreto por linea." -ForegroundColor Red
    exit 1
}
$secrets = Get-Content $secretsFile | Where-Object { $_.Trim() -ne '' }
if (!$secrets) { Write-Host ".purge-secrets.txt esta vacio." -ForegroundColor Red; exit 1 }

if (git status --porcelain) {
    Write-Host "Hay cambios sin commitear - hace commit o stash antes de purgar." -ForegroundColor Red
    exit 1
}

# sed (BRE) en el sh interno de git: escapar cada secreto para match literal
$sedFile = Join-Path $root '.purge-sed.sed'
$secrets | ForEach-Object {
    $lit = [regex]::Escape($_) -replace '\\\|', '|'   # \| en BRE es alternancia; | literal va sin escape
    "s|$lit|***REMOVED***|g"
} | Set-Content -Encoding ASCII $sedFile

# ruta estilo msys (/c/xampp/...) que entiende el sh de filter-branch
$sedFw = $sedFile -replace '\\', '/'
$sedSh = '/' + $sedFw.Substring(0, 1).ToLower() + $sedFw.Substring(2)

git branch -f backup-pre-purge HEAD 2>$null | Out-Null
Write-Host "Backup local: rama backup-pre-purge" -ForegroundColor DarkGray
Write-Host "Reescribiendo historial ($($secrets.Count) secreto(s))..." -ForegroundColor Cyan

$find = "find . -type f \( -name '*.php' -o -name '*.md' -o -name '*.ps1' -o -name '*.js' -o -name '*.css' -o -name '*.sql' -o -name '*.json' -o -name '*.txt' -o -name '*.xml' -o -name '*.html' -o -name '*.svg' \)"
$env:FILTER_BRANCH_SQUELCH_WARNING = '1'
git filter-branch --force --tree-filter "$find -exec sed -i -f '$sedSh' {} +" --tag-name-filter cat -- --all
if ($LASTEXITCODE -ne 0) {
    Write-Host "filter-branch fallo - el repo sigue en backup-pre-purge." -ForegroundColor Red
    exit 1
}

# limpiar refs de respaldo + reflog para que el secreto no quede en packs
git for-each-ref --format='%(refname)' refs/original/ | ForEach-Object { git update-ref -d $_ }
git reflog expire --expire=now --all
git gc --prune=now --aggressive | Out-Null

git checkout -- .   # arbol de trabajo = HEAD ya filtrado

Remove-Item $sedFile -ErrorAction SilentlyContinue
Remove-Item $secretsFile -ErrorAction SilentlyContinue

if ($Push) {
    Write-Host "Force-push a origin/main..." -ForegroundColor Cyan
    git push --force origin main
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Push fallo - hace 'git push --force origin main' a mano." -ForegroundColor Red
        exit 1
    }
    Write-Host "Push OK." -ForegroundColor Green
}

Write-Host "`nPurge listo - credenciales fuera del historial." -ForegroundColor Green
Write-Host "Pendiente: cada clone debe hacer 'git fetch; git reset --hard origin/main' o re-clonar." -ForegroundColor Yellow
Write-Host "Backup local 'backup-pre-purge' - borralo cuando veas que todo anda: git branch -D backup-pre-purge" -ForegroundColor DarkGray
