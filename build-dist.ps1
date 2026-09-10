$ErrorActionPreference = "Stop"

$projectRoot = $PSScriptRoot
$distPath = Join-Path $projectRoot "dist"

$publicFiles = @(
    "index.html"
    "robots.txt"
    "sitemap.xml"
)

$publicDirectories = @(
    "assets"
    "css"
    "js"
    "blog"
)

if (Test-Path $distPath) {
    Remove-Item -Path $distPath -Recurse -Force
}

New-Item -ItemType Directory -Path $distPath | Out-Null

foreach ($file in $publicFiles) {
    $sourcePath = Join-Path $projectRoot $file

    if (-not (Test-Path $sourcePath -PathType Leaf)) {
        throw "Arquivo publico obrigatorio nao encontrado: $file"
    }

    Copy-Item -Path $sourcePath -Destination $distPath
}

foreach ($directory in $publicDirectories) {
    $sourcePath = Join-Path $projectRoot $directory

    if (-not (Test-Path $sourcePath -PathType Container)) {
        throw "Pasta publica obrigatoria nao encontrada: $directory"
    }

    Copy-Item -Path $sourcePath -Destination $distPath -Recurse
}

Write-Host "Pacote de publicacao criado com sucesso em: $distPath"