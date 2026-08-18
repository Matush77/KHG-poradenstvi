[CmdletBinding()]
param(
    [switch]$Check
)

$ErrorActionPreference = 'Stop'

$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$repositoryRoot = (Resolve-Path (Join-Path $scriptDirectory '..')).Path
$partialPath = Join-Path $repositoryRoot 'partials\footer.html'

if (-not (Test-Path -LiteralPath $partialPath -PathType Leaf)) {
    throw "Footer partial was not found: $partialPath"
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
$footerTemplate = [System.IO.File]::ReadAllText($partialPath, $utf8WithoutBom).TrimEnd([char[]]"`r`n")
$footerPattern = [regex]::new(
    '<footer\b[^>]*>[\s\S]*?</footer>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$htmlFiles = Get-ChildItem -LiteralPath $repositoryRoot -Filter '*.html' -File | Sort-Object Name

if ($htmlFiles.Count -eq 0) {
    throw "No top-level HTML files were found in $repositoryRoot"
}

$changedFiles = @()

foreach ($file in $htmlFiles) {
    if ($file.DirectoryName -ne $repositoryRoot) {
        throw "Refusing to process a file outside the repository root: $($file.FullName)"
    }

    $content = [System.IO.File]::ReadAllText($file.FullName, $utf8WithoutBom)
    $matches = $footerPattern.Matches($content)

    if ($matches.Count -ne 1) {
        throw "Expected exactly one footer in $($file.Name), found $($matches.Count)."
    }

    $match = $matches[0]
    $lineStart = $content.LastIndexOf("`n", [Math]::Max(0, $match.Index - 1)) + 1
    $indent = $content.Substring($lineStart, $match.Index - $lineStart)

    if ($indent -notmatch '^[\t ]*$') {
        throw "Footer in $($file.Name) does not start on its own indented line."
    }

    $lineEnding = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }
    $templateLines = [regex]::Split($footerTemplate, '\r?\n')
    $renderedLines = @($templateLines[0])

    if ($templateLines.Count -gt 1) {
        $renderedLines += $templateLines[1..($templateLines.Count - 1)] | ForEach-Object {
            if ([string]::IsNullOrEmpty($_)) { '' } else { $indent + $_ }
        }
    }

    $footerForFile = $renderedLines -join $lineEnding
    $replaceFooter = [System.Text.RegularExpressions.MatchEvaluator]{
        param($currentMatch)
        return $footerForFile
    }
    $updatedContent = $footerPattern.Replace($content, $replaceFooter, 1)

    if ($updatedContent -cne $content) {
        $changedFiles += $file.Name

        if (-not $Check) {
            [System.IO.File]::WriteAllText($file.FullName, $updatedContent, $utf8WithoutBom)
        }
    }
}

if ($Check -and $changedFiles.Count -gt 0) {
    throw "Footer is out of sync in: $($changedFiles -join ', ')"
}

if ($Check) {
    Write-Output "Footer is synchronized across $($htmlFiles.Count) HTML files."
} else {
    Write-Output "Synchronized footer across $($htmlFiles.Count) HTML files; updated $($changedFiles.Count)."
}
