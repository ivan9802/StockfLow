#Requires -Version 7.0
<#
.SYNOPSIS
    Démarrage quotidien de StockFlow sur un poste déjà installé (scripts/install.ps1).

.DESCRIPTION
    Démarre les conteneurs, attend que l'application réponde, puis ouvre l'adresse dans
    le navigateur. Arrêt en fin de journée : docker compose stop (les données sont conservées).
    Équivalent Linux, macOS et Git Bash : scripts/start.sh.

.PARAMETER NoOpen
    N'ouvre pas le navigateur.

.EXAMPLE
    pwsh -File scripts/start.ps1
#>
[CmdletBinding()]
param([switch]$NoOpen)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location (Split-Path -Parent $PSScriptRoot)

function Stop-Start([string]$Message) {
    Write-Host "ERREUR : $Message" -ForegroundColor Red
    exit 1
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { Stop-Start 'Docker est introuvable.' }
& docker info *> $null
if ($LASTEXITCODE -ne 0) { Stop-Start 'Le démon Docker ne répond pas. Lancez Docker Desktop puis relancez.' }
if (-not (Test-Path .env)) { Stop-Start '.env absent : lancez d''abord pwsh -File scripts/install.ps1.' }

$appPort = [Environment]::GetEnvironmentVariable('APP_PORT')
if (-not $appPort) {
    $line = Get-Content .env | Where-Object { $_ -match '^APP_PORT=' } | Select-Object -Last 1
    if ($line) { $appPort = ($line -replace '^APP_PORT=', '').Trim().Trim('"') }
}
if (-not $appPort) { $appPort = '8080' }
$url = "http://localhost:$appPort"

Write-Host 'Démarrage des conteneurs (après un redémarrage du poste, MySQL peut demander quelques minutes)...'
& docker compose up -d --wait --wait-timeout 600
if ($LASTEXITCODE -ne 0) { Stop-Start 'Les conteneurs n''ont pas démarré. Voir « docker compose ps » et « docker compose logs ».' }

$status = 0
for ($i = 0; $i -lt 60; $i++) {
    try {
        $status = [int](Invoke-WebRequest -Uri "$url/up" -UseBasicParsing -TimeoutSec 5 -SkipHttpErrorCheck).StatusCode
    } catch {
        $status = 0
    }
    if ($status -eq 200) { break }
    Start-Sleep -Seconds 2
}
if ($status -ne 200) { Stop-Start "$url/up répond $status. Voir « docker compose logs app web »." }

Write-Host "StockFlow répond sur $url" -ForegroundColor Green
if (-not $NoOpen) {
    Start-Process $url
}
