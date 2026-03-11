# Build Script: Generate Free and Pro distributions
# Usage:
#   .\scripts\build.ps1 -Type free
#   .\scripts\build.ps1 -Type pro
#   .\scripts\build.ps1 -Type all

param(
    [ValidateSet('free', 'pro', 'all')]
    [string]$Type = 'all'
)

$ErrorActionPreference = 'Stop'

$rootDir = Split-Path -Parent $PSScriptRoot
$buildsDir = Join-Path $rootDir 'builds'
$tempDir = Join-Path $buildsDir '.temp'

$freeRemovePaths = @(
    'includes\stripe',
    'includes\google',
    'includes\outlook',
    'includes\post-types\class-staff.php',
    'includes\webhook\class-stripe-webhook.php',
    'includes\calendar\providers\class-google-provider.php',
    'includes\calendar\providers\class-outlook-provider.php',
    'vendor\stripe-php'
)

$topLevelDirs = @('assets', 'docs', 'includes', 'vendor')

function Get-PluginVersion {
    $pluginFile = Join-Path $rootDir 'simple-booking.php'
    if (-not (Test-Path $pluginFile)) {
        throw "Plugin file not found: $pluginFile"
    }

    $versionLine = Get-Content $pluginFile | Where-Object { $_ -match 'Version:\s+(\d+\.\d+\.\d+)' } | Select-Object -First 1
    if (-not $versionLine) {
        throw 'Unable to detect plugin version from simple-booking.php'
    }

    $null = $versionLine -match 'Version:\s+(\d+\.\d+\.\d+)'
    return $Matches[1]
}

function Initialize-TempDir {
    if (Test-Path $tempDir) {
        Remove-Item -Path $tempDir -Recurse -Force
    }
    New-Item -Path $tempDir -ItemType Directory -Force | Out-Null
}

function Copy-PluginTo {
    param(
        [Parameter(Mandatory = $true)]
        [string]$destination
    )

    New-Item -Path $destination -ItemType Directory -Force | Out-Null

    foreach ($dir in $topLevelDirs) {
        $src = Join-Path $rootDir $dir
        if (Test-Path $src) {
            Copy-Item -Path $src -Destination (Join-Path $destination $dir) -Recurse -Force
        }
    }

    Get-ChildItem -Path $rootDir -File -Force | Where-Object {
        $_.Name -notin @('.gitignore', '.DS_Store')
    } | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $destination $_.Name) -Force
    }
}

function Remove-FreeOnlyPaths {
    param(
        [Parameter(Mandatory = $true)]
        [string]$pluginDir
    )

    foreach ($rel in $freeRemovePaths) {
        $target = Join-Path $pluginDir $rel
        if (Test-Path $target) {
            Remove-Item -Path $target -Recurse -Force
        }
    }
}

function New-DistributionZip {
    param(
        [Parameter(Mandatory = $true)]
        [string]$sourceDir,
        [Parameter(Mandatory = $true)]
        [string]$zipPath
    )

    Add-Type -AssemblyName 'System.IO.Compression.FileSystem'

    if (Test-Path $zipPath) {
        Remove-Item -Path $zipPath -Force
    }

    [System.IO.Compression.ZipFile]::CreateFromDirectory($sourceDir, $zipPath, [System.IO.Compression.CompressionLevel]::Optimal, $false)
}

try {
    if (-not (Test-Path $buildsDir)) {
        New-Item -Path $buildsDir -ItemType Directory -Force | Out-Null
    }

    $version = Get-PluginVersion
    Write-Host "Building Simple Booking v$version" -ForegroundColor Green
    Write-Host ''

    if ($Type -eq 'free' -or $Type -eq 'all') {
        Write-Host '[1/2] Building Free distribution...' -ForegroundColor Cyan
        Initialize-TempDir

        $stageDir = Join-Path $tempDir 'free'
        $pluginStageDir = Join-Path $stageDir 'simple-booking'

        Copy-PluginTo -destination $pluginStageDir
        Remove-FreeOnlyPaths -pluginDir $pluginStageDir

        $zipPath = Join-Path $buildsDir "simple-booking-free-$version.zip"
        New-DistributionZip -sourceDir $stageDir -zipPath $zipPath

        Write-Host "? Free distribution created: $(Split-Path -Leaf $zipPath)" -ForegroundColor Green
    }

    if ($Type -eq 'pro' -or $Type -eq 'all') {
        $step = if ($Type -eq 'all') { '[2/2]' } else { '[1/1]' }
        Write-Host "$step Building Pro distribution..." -ForegroundColor Cyan
        Initialize-TempDir

        $stageDir = Join-Path $tempDir 'pro'
        $pluginStageDir = Join-Path $stageDir 'simple-booking'

        Copy-PluginTo -destination $pluginStageDir

        $zipPath = Join-Path $buildsDir "simple-booking-pro-$version.zip"
        New-DistributionZip -sourceDir $stageDir -zipPath $zipPath

        Write-Host "? Pro distribution created: $(Split-Path -Leaf $zipPath)" -ForegroundColor Green
    }

    if (Test-Path $tempDir) {
        Remove-Item -Path $tempDir -Recurse -Force
    }

    Write-Host ''
    Write-Host '? Build complete!' -ForegroundColor Green
    Write-Host 'Distributions available in: builds/' -ForegroundColor Cyan
}
catch {
    Write-Host "? Build failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    exit 1
}
