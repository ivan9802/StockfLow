#Requires -Version 7.0
<#
.SYNOPSIS
    Installation locale de StockFlow (développement ou démonstration), depuis un clone neuf.

.DESCRIPTION
    Vérifie Docker et Docker Compose, crée .env s'il n'existe pas, construit et démarre
    les conteneurs, attend MySQL, installe les dépendances PHP et JavaScript, génère la
    clé de l'application si elle est vide, compile les CSS et JavaScript, applique les
    migrations et charge les données de référence. Avec -Demo, charge en plus le jeu de
    démonstration.

    Le script se relance sans danger : il n'écrase jamais un .env existant, ne régénère
    pas une APP_KEY déjà présente et ne recharge la démonstration que si on le demande.
    Il s'arrête à la première erreur.

    Ports : APP_PORT, FORWARD_DB_PORT et VITE_PORT sont lus dans .env. Une variable
    d'environnement du même nom l'emporte.

    Équivalent Linux, macOS et Git Bash : scripts/install.sh.
    Documentation : docs/07-deployment/local-install.md.

.PARAMETER Demo
    Remplace la base de développement par le jeu de démonstration (quelques
    minutes). Si la base contient déjà des comptes, une confirmation est demandée.

.PARAMETER Yes
    Répond oui à la confirmation de -Demo.

.PARAMETER Check
    Contrôle seulement les prérequis, puis s'arrête.

.EXAMPLE
    pwsh -File scripts/install.ps1
.EXAMPLE
    pwsh -File scripts/install.ps1 -Demo
#>
[CmdletBinding()]
param(
    [switch]$Demo,
    [switch]$Yes,
    [switch]$Check
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
# Une commande externe (docker) en échec arrête aussi le script.
$PSNativeCommandUseErrorActionPreference = $true

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$script:StepNo = 0
function Write-Step([string]$Text) {
    $script:StepNo++
    Write-Host ''
    Write-Host "[$script:StepNo] $Text" -ForegroundColor Cyan
}

function Stop-Install([string]$Message) {
    throw $Message
}

# Valeur d'une variable : environnement d'abord, puis .env, puis valeur par défaut.
function Get-EnvValue([string]$Name, [string]$Default) {
    $value = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrEmpty($value) -and (Test-Path .env)) {
        $line = Get-Content .env | Where-Object { $_ -match "^$Name=" } | Select-Object -Last 1
        if ($line) { $value = ($line -replace "^$Name=", '').Trim().Trim('"') }
    }
    if ([string]::IsNullOrEmpty($value)) { return $Default }
    return $value
}

function Test-Prerequisites {
    # Ici les codes de sortie sont lus un par un, pour un message précis.
    $PSNativeCommandUseErrorActionPreference = $false
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Stop-Install 'Docker est introuvable. Installez Docker Desktop.'
    }
    & docker info *> $null
    if ($LASTEXITCODE -ne 0) {
        Stop-Install 'Le démon Docker ne répond pas. Lancez Docker Desktop puis relancez.'
    }
    $composeVersion = (& docker compose version --short 2>$null)
    if ($LASTEXITCODE -ne 0 -or -not $composeVersion) {
        Stop-Install 'Docker Compose v2 (commande « docker compose ») est introuvable.'
    }
    if ($composeVersion -match '^v?1\.') {
        Stop-Install "Docker Compose $composeVersion est trop ancien : la version 2 au moins est requise."
    }
    foreach ($file in 'compose.yaml', '.env.example', 'docker/php/Dockerfile') {
        if (-not (Test-Path $file)) { Stop-Install "Fichier du projet introuvable : $file (dossier $Root)." }
    }
    $serverVersion = (& docker version --format '{{.Server.Version}}')
    Write-Host "    Docker $serverVersion, Docker Compose $composeVersion, projet dans $Root"
}

function Invoke-Artisan {
    & docker compose exec -T app php artisan @args
}

# Nombre de comptes dans la base de développement (0 si la table n'existe pas encore).
function Get-UserCount {
    $PSNativeCommandUseErrorActionPreference = $false
    $query = 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -N -B -u"$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM users" 2>/dev/null || echo 0'
    $out = & docker compose exec -T db sh -c $query 2>$null
    if (-not $out) { return 0 }
    return [int](($out | Select-Object -Last 1).Trim())
}

function Get-HttpStatus([string]$Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5 -SkipHttpErrorCheck
        return [int]$response.StatusCode
    } catch {
        return 0
    }
}

try {
    Write-Step 'Vérification des prérequis'
    Test-Prerequisites
    if ($Check) {
        Write-Host '    Prérequis présents.'
        exit 0
    }

    Write-Step 'Fichier de configuration .env'
    if (Test-Path .env) {
        Write-Host '    .env existe déjà : conservé tel quel.'
    } else {
        Copy-Item .env.example .env
        Write-Host '    .env créé à partir de .env.example.'
    }
    if (Select-String -Path .env -Pattern '^APP_ENV=production' -Quiet) {
        Stop-Install '.env indique APP_ENV=production : ce script installe un poste de développement. Pour la production, voir scripts/deploy.sh.'
    }

    $appPort = Get-EnvValue 'APP_PORT' '8080'

    Write-Step 'Construction et démarrage des conteneurs (la première fois : plusieurs minutes)'
    # --wait rend la main quand MySQL répond à son contrôle de santé.
    & docker compose up -d --build --wait --wait-timeout 600

    Write-Step 'Attente de MySQL'
    $ready = $false
    $PSNativeCommandUseErrorActionPreference = $false
    for ($i = 0; $i -lt 60; $i++) {
        & docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent' *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 5
    }
    if (-not $ready) { Stop-Install 'MySQL ne répond pas après 5 minutes. Voir « docker compose logs db ».' }
    $PSNativeCommandUseErrorActionPreference = $true
    Write-Host '    MySQL répond.'

    Write-Step 'Dépendances PHP (composer install)'
    & docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress

    Write-Step "Clé de l'application"
    if (Select-String -Path .env -Pattern '^APP_KEY=base64:.+' -Quiet) {
        Write-Host '    APP_KEY déjà définie : conservée.'
    } else {
        Invoke-Artisan key:generate --force
    }

    Write-Step 'Dépendances JavaScript (npm ci)'
    & docker compose exec -T app npm ci --no-audit --no-fund

    Write-Step 'Compilation des CSS et JavaScript (npm run build, jusqu''à quelques minutes sous Windows)'
    & docker compose exec -T app npm run build

    $seedOutput = ''
    if ($Demo) {
        $users = Get-UserCount
        if ($users -gt 0) {
            Write-Host ''
            Write-Host "La base de développement contient $users compte(s). Le jeu de démonstration la remplace entièrement." -ForegroundColor Yellow
            if (-not $Yes) {
                if ([Environment]::UserInteractive -and -not [Console]::IsInputRedirected) {
                    $answer = Read-Host 'Taper OUI pour continuer'
                    if ($answer -cne 'OUI') { Stop-Install "Chargement de la démonstration abandonné. La base n'a pas été modifiée." }
                } else {
                    Stop-Install 'Confirmation impossible sans terminal : relancez avec -Demo -Yes.'
                }
            }
        }
        Write-Step 'Base de démonstration (migrate:fresh et DemoSeeder, quelques minutes)'
        Invoke-Artisan migrate:fresh --force --seed --seeder=DemoSeeder
        Write-Step 'Contrôle du stock'
        Invoke-Artisan stock:verify
    } else {
        Write-Step 'Migrations'
        Invoke-Artisan migrate --force
        Write-Step 'Données de référence (emplacements, premier administrateur)'
        $seedOutput = (Invoke-Artisan db:seed --force | Out-String)
        Write-Host $seedOutput
    }

    Write-Step "Contrôle de l'application"
    $status = 0
    for ($i = 0; $i -lt 30; $i++) {
        $status = Get-HttpStatus "http://localhost:$appPort/up"
        if ($status -eq 200) { break }
        Start-Sleep -Seconds 2
    }
    if ($status -ne 200) { Stop-Install "http://localhost:$appPort/up répond $status. Voir « docker compose logs app web »." }
    Write-Host "    http://localhost:$appPort/up : 200"
} catch {
    Write-Host ''
    Write-Host "ERREUR : $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Installation interrompue. Corrigez la cause ci-dessus puis relancez le script : il reprend sans casse.' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host 'Installation terminée.' -ForegroundColor Green
Write-Host ''
Write-Host "    Adresse : http://localhost:$appPort"
if ($Demo) {
    Write-Host '    Comptes de démonstration, mot de passe commun : demo-stockflow-2026'
    Write-Host '      Administrateur        admin@stockflow.test'
    Write-Host '      Gestionnaire de stock awa.kone@stockflow.test'
    Write-Host '      Vendeur               aminata.diallo@stockflow.test'
} else {
    # L'adresse n'est annoncée que si le seeder vient de créer le compte : elle est lue
    # dans sa propre ligne, pas dans .env (un administrateur existant peut en avoir une autre).
    if ($seedOutput -match 'Compte administrateur créé : ([^\s,]+)') {
        Write-Host "    Administrateur : $($Matches[1])"
        if ($seedOutput -match 'mot de passe tiré de ADMIN_PASSWORD') {
            Write-Host '    Mot de passe provisoire : la valeur de ADMIN_PASSWORD dans .env (le seeder ne la réaffiche pas).'
            Write-Host '    Il doit être changé à la première connexion ; videz ensuite ADMIN_PASSWORD dans .env.'
        } else {
            Write-Host '    Mot de passe provisoire : celui de la ligne « Compte administrateur créé » ci-dessus.'
            Write-Host "    Notez-le maintenant : il n'est affiché qu'une fois et doit être changé à la première connexion."
        }
    } else {
        Write-Host '    Un administrateur existe déjà : compte inchangé, connectez-vous avec ses identifiants habituels.'
    }
    Write-Host '    Jeu de démonstration : pwsh -File scripts/install.ps1 -Demo'
}
Write-Host ''
Write-Host 'Démarrage des jours suivants : pwsh -File scripts/start.ps1'
