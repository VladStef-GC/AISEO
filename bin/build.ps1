<#
.SYNOPSIS
    Build a clean, distribution-ready ZIP of the SEO Captain plugin on Windows.

.DESCRIPTION
    Windows/PowerShell equivalent of bin/build.sh for local builds. Produces the
    FULL premium source zip (Freemius' server generates the stripped Free build);
    only development/CI files are excluded.

.EXAMPLE
    pwsh bin/build.ps1
    Output: dist\ai-seo-captain.zip  (top-level folder: ai-seo-captain\)
#>

$ErrorActionPreference = 'Stop'

$slug  = 'ai-seo-captain'
$root  = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$build = Join-Path $root 'build'
$stage = Join-Path $build $slug
$dist  = Join-Path $root 'dist'
$zip   = Join-Path $dist "$slug.zip"

Write-Host '==> Cleaning previous build output'
Remove-Item $build, $dist -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $stage, $dist | Out-Null

Write-Host '==> Staging plugin files (excluding dev/CI artifacts)'
# The whole vendor\ tree is excluded (Composer dev deps bloat the zip and are
# never loaded at runtime); only vendor\freemius is re-added afterwards.
$excludeDirs = @('.git', '.github', 'build', 'dist', 'bin', 'node_modules', 'vendor', 'tests', 'docs') |
    ForEach-Object { Join-Path $root $_ }
$excludeFiles = @(
    'test-steps.php', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
    'composer.json', 'composer.lock', 'composer.phar', '.gitignore', '.gitattributes',
    '.DS_Store', 'Thumbs.db', '*.md'
)

# robocopy mirrors the tree while honouring directory/file exclusions.
robocopy $root $stage /E /XD $excludeDirs /XF $excludeFiles /NFL /NDL /NJH /NJS /NP | Out-Null
# robocopy exit codes < 8 indicate success; anything >= 8 is a real failure.
if ($LASTEXITCODE -ge 8) { throw "robocopy failed (exit $LASTEXITCODE)" }
$global:LASTEXITCODE = 0

# Re-add only the Freemius SDK (required at runtime).
$freemius = Join-Path $root 'vendor\freemius'
if (Test-Path $freemius) {
    Write-Host '==> Adding vendor\freemius (required runtime SDK)'
    robocopy $freemius (Join-Path $stage 'vendor\freemius') /E /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy (freemius) failed (exit $LASTEXITCODE)" }
    $global:LASTEXITCODE = 0
}

Write-Host "==> Creating $zip"
# Build the archive manually so entry paths use forward slashes (the ZIP-spec
# separator). Compress-Archive / .NET Framework write backslashes on Windows,
# which some unzippers and WordPress installs mishandle.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stageParent = (Split-Path $stage -Parent)
    Get-ChildItem $stage -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($stageParent.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $_.FullName, $rel,
            [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}

$size = '{0:N2} MB' -f ((Get-Item $zip).Length / 1MB)
Write-Host "==> Done. $size  $zip"
