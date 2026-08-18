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

$footerTemplate = [System.IO.File]::ReadAllText($partialPath).TrimEnd([char[]]"`r`n")
$footerPattern = [regex]::new(
    '<footer\b[\s\S]*?</footer>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$utf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
$htmlFiles = Get-ChildItem -LiteralPath $repositoryRoot -Filter '*.html' -File | Sort-Object Name

if ($htmlFiles.Count -eq 0) {
    throw "No top-level HTML files were found in $repositoryRoot"
}

$changedFiles = [System.Collections.Generic.List[string]]::new()

foreach ($file in $htmlFiles) {
    if ($file.DirectoryName -ne $repositoryRoot) {
        throw "Refusing to process a file outside the repository root: $($file.FullName)"
    }

    $content = [System.IO.File]::ReadAllText($file.FullName)
    $matches = $footerPattern.Matches($content)

    if ($matches.Count -ne 1) {
        throw "Expected exactly one footer in $($file.Name), found $($matches.Count)."
    }

    $lineEnding = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }
    $footerForFile = [regex]::Replace($footerTemplate, '\r?\n', $lineEnding)
    $replaceFooter = [System.Text.RegularExpressions.MatchEvaluator]{
        param($match)
        return $footerForFile
    }
    $updatedContent = $footerPattern.Replace($content, $replaceFooter, 1)

    if ($updatedContent -cne $content) {
        $changedFiles.Add($file.Name)

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
