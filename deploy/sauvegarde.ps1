# Sauvegarde : dump de la base + dossier des fichiers privés. Usage : .\deploy\sauvegarde.ps1 -Destination D:\sauvegardes
# Identifiants lus dans .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD) ; mysqldump doit être dans le PATH.
param([Parameter(Mandatory)][string]$Destination, [int]$ConserverJours = 30)

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $PSScriptRoot
$env_ = @{}
Get-Content (Join-Path $racine '.env') | Where-Object { $_ -match '^\s*[A-Z_]+=' } | ForEach-Object {
    $k, $v = $_ -split '=', 2
    $env_[$k.Trim()] = $v.Trim().Trim('"')
}

$horodatage = Get-Date -Format 'yyyyMMdd-HHmmss'
$dossier = Join-Path $Destination $horodatage
New-Item -ItemType Directory -Force $dossier | Out-Null

$args_ = @("--user=$($env_['DB_USERNAME'])", '--single-transaction', '--routines', '--result-file', (Join-Path $dossier 'base.sql'), $env_['DB_DATABASE'])
if ($env_['DB_PASSWORD']) { $args_ = @("--password=$($env_['DB_PASSWORD'])") + $args_ }
& mysqldump @args_
if ($LASTEXITCODE -ne 0) { throw 'mysqldump a échoué.' }

Compress-Archive -Path (Join-Path $racine 'storage\app\private\*') -DestinationPath (Join-Path $dossier 'fichiers-prives.zip') -Force

Get-ChildItem $Destination -Directory | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$ConserverJours) } | Remove-Item -Recurse -Force
Write-Output "Sauvegarde créée : $dossier"
