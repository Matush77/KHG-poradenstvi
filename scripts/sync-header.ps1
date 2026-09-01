[CmdletBinding()]
param(
    [switch]$Check
)

$ErrorActionPreference = 'Stop'

$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$repositoryRoot = (Resolve-Path (Join-Path $scriptDirectory '..')).Path
$headerPartialPath = Join-Path $repositoryRoot 'partials\header.html'
$modalPartialPath = Join-Path $repositoryRoot 'partials\booking-modal.html'

foreach ($partialPath in @($headerPartialPath, $modalPartialPath)) {
    if (-not (Test-Path -LiteralPath $partialPath -PathType Leaf)) {
        throw "Shared partial was not found: $partialPath"
    }
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
$headerTemplate = [System.IO.File]::ReadAllText($headerPartialPath, $utf8WithoutBom).TrimEnd([char[]]"`r`n")
$modalTemplate = [System.IO.File]::ReadAllText($modalPartialPath, $utf8WithoutBom).TrimEnd([char[]]"`r`n")
$headerPattern = [regex]::new(
    '<header\b[^>]*class="header"[^>]*>[\s\S]*?</header>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$modalStartPattern = [regex]::new(
    '<div\b(?=[^>]*\bid\s*=\s*["'']bookingModal["''])[^>]*>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$divTokenPattern = [regex]::new(
    '<div\b[^>]*>|</div\s*>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$galleryMarkerPattern = [regex]::new(
    '(?m)^[\t ]*<!--\s*Office Gallery Lightbox\s*-->',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$privacyScriptPattern = [regex]::new(
    '(?m)^[\t ]*<script\b[^>]*src="js/privacy-consent\.js[^>]*></script>',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$htmlFiles = Get-ChildItem -LiteralPath $repositoryRoot -Filter '*.html' -File | Sort-Object Name

if ($htmlFiles.Count -eq 0) {
    throw "No top-level HTML files were found in $repositoryRoot"
}

function Get-LineIndent {
    param(
        [string]$Content,
        [int]$Index
    )

    $lineStart = $Content.LastIndexOf("`n", [Math]::Max(0, $Index - 1)) + 1
    $indent = $Content.Substring($lineStart, $Index - $lineStart)

    if ($indent -notmatch '^[\t ]*$') {
        throw 'Shared component does not start on its own indented line.'
    }

    return $indent
}

function Add-RelativeIndent {
    param(
        [string]$Template,
        [string]$Indent,
        [string]$LineEnding
    )

    $templateLines = [regex]::Split($Template, '\r?\n')
    $renderedLines = @($templateLines[0])

    if ($templateLines.Count -gt 1) {
        $renderedLines += $templateLines[1..($templateLines.Count - 1)] | ForEach-Object {
            if ([string]::IsNullOrEmpty($_)) { '' } else { $Indent + $_ }
        }
    }

    return $renderedLines -join $LineEnding
}

function Add-AbsoluteIndent {
    param(
        [string]$Template,
        [string]$Indent,
        [string]$LineEnding
    )

    return ([regex]::Split($Template, '\r?\n') | ForEach-Object {
        if ([string]::IsNullOrEmpty($_)) { '' } else { $Indent + $_ }
    }) -join $LineEnding
}

function Get-BookingModalRanges {
    param([string]$Content)

    $ranges = @()
    $searchIndex = 0

    while ($searchIndex -lt $Content.Length) {
        $modalStart = $modalStartPattern.Match($Content, $searchIndex)

        if (-not $modalStart.Success) {
            break
        }

        $depth = 0
        $token = $divTokenPattern.Match($Content, $modalStart.Index)
        $modalEnd = -1

        while ($token.Success) {
            if ($token.Value -match '^</div') {
                $depth--
            } else {
                $depth++
            }

            if ($depth -eq 0) {
                $modalEnd = $token.Index + $token.Length
                break
            }

            $token = $divTokenPattern.Match($Content, $token.Index + $token.Length)
        }

        if ($modalEnd -lt 0) {
            throw "Booking modal starting at character $($modalStart.Index) has no matching closing div."
        }

        $rangeStart = $modalStart.Index
        $lineStart = $Content.LastIndexOf("`n", [Math]::Max(0, $rangeStart - 1)) + 1

        if ($Content.Substring($lineStart, $rangeStart - $lineStart) -match '^[\t ]*$') {
            $rangeStart = $lineStart
        }

        $prefix = $Content.Substring(0, $rangeStart)
        $commentMatch = [regex]::Match(
            $prefix,
            '<!--[^\r\n]*(?:Booking Modal|booking-modal\.html)[^\r\n]*-->[\t ]*\r?\n[\t ]*$',
            [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
        )

        if ($commentMatch.Success) {
            $commentLineStart = $prefix.LastIndexOf("`n", [Math]::Max(0, $commentMatch.Index - 1)) + 1
            $rangeStart = $commentLineStart
        }

        $trailingWhitespace = [regex]::Match(
            $Content.Substring($modalEnd),
            '^\r?\n(?:[\t ]*\r?\n)*'
        )
        $rangeEnd = $modalEnd + $trailingWhitespace.Length

        $ranges += [pscustomobject]@{
            Start = $rangeStart
            End = $rangeEnd
        }
        $searchIndex = $modalEnd
    }

    return $ranges
}

function Remove-BookingModals {
    param([string]$Content)

    $ranges = @(Get-BookingModalRanges -Content $Content)

    for ($index = $ranges.Count - 1; $index -ge 0; $index--) {
        $range = $ranges[$index]
        $Content = $Content.Remove($range.Start, $range.End - $range.Start)
    }

    return $Content
}

function Get-HeaderForFile {
    param([string]$FileName)

    $activeSection = ''
    $currentValue = ''

    if ($FileName -eq 'cenik.html') {
        $activeSection = 'CENIK'
        $currentValue = 'page'
    } elseif ($FileName -eq 'blog.html') {
        $activeSection = 'BLOG'
        $currentValue = 'page'
    } elseif ($FileName -match '^blog-.*\.html$') {
        $activeSection = 'BLOG'
        $currentValue = 'location'
    } elseif ($FileName -eq 'kviz.html') {
        $activeSection = 'QUIZ'
        $currentValue = 'page'
    } elseif ($FileName -match '^quiz-.*\.html$') {
        $activeSection = 'QUIZ'
        $currentValue = 'location'
    }

    $rendered = $headerTemplate

    foreach ($section in @('CENIK', 'BLOG', 'QUIZ')) {
        $isActive = $section -eq $activeSection
        $rendered = $rendered.Replace("{{$($section)_ACTIVE}}", $(if ($isActive) { ' active' } else { '' }))
        $rendered = $rendered.Replace("{{$($section)_CURRENT}}", $(if ($isActive) { " aria-current=`"$currentValue`"" } else { '' }))
    }

    if ($rendered -match '{{[A-Z_]+}}') {
        throw "Unresolved header placeholder for $FileName"
    }

    return $rendered
}

$changedFiles = @()

foreach ($file in $htmlFiles) {
    if ($file.DirectoryName -ne $repositoryRoot) {
        throw "Refusing to process a file outside the repository root: $($file.FullName)"
    }

    $content = [System.IO.File]::ReadAllText($file.FullName, $utf8WithoutBom)
    $lineEnding = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }
    $headerMatches = $headerPattern.Matches($content)

    if ($headerMatches.Count -ne 1) {
        throw "Expected exactly one main header in $($file.Name), found $($headerMatches.Count)."
    }

    $headerMatch = $headerMatches[0]
    $headerIndent = Get-LineIndent -Content $content -Index $headerMatch.Index
    $headerForFile = Add-RelativeIndent -Template (Get-HeaderForFile -FileName $file.Name) -Indent $headerIndent -LineEnding $lineEnding
    $updatedContent = $headerPattern.Replace(
        $content,
        [System.Text.RegularExpressions.MatchEvaluator]{ param($match) return $headerForFile },
        1
    )

    $updatedContent = Remove-BookingModals -Content $updatedContent
    $galleryMatches = $galleryMarkerPattern.Matches($updatedContent)

    if ($galleryMatches.Count -gt 1) {
        throw "Expected at most one office gallery insertion point in $($file.Name), found $($galleryMatches.Count)."
    }

    if ($galleryMatches.Count -eq 1) {
        $insertionMatch = $galleryMatches[0]
    } else {
        $scriptMatches = $privacyScriptPattern.Matches($updatedContent)

        if ($scriptMatches.Count -ne 1) {
            throw "Expected one privacy script insertion point in $($file.Name), found $($scriptMatches.Count)."
        }

        $insertionMatch = $scriptMatches[0]
    }

    $modalIndent = [regex]::Match($insertionMatch.Value, '^[\t ]*').Value
    $modalForFile = Add-AbsoluteIndent -Template $modalTemplate -Indent $modalIndent -LineEnding $lineEnding
    $updatedContent = $updatedContent.Insert($insertionMatch.Index, $modalForFile + $lineEnding + $lineEnding)

    if ($updatedContent -cne $content) {
        $changedFiles += $file.Name

        if (-not $Check) {
            [System.IO.File]::WriteAllText($file.FullName, $updatedContent, $utf8WithoutBom)
        }
    }
}

if ($Check -and $changedFiles.Count -gt 0) {
    throw "Header or booking modal is out of sync in: $($changedFiles -join ', ')"
}

if ($Check) {
    Write-Output "Header and booking modal are synchronized across $($htmlFiles.Count) HTML files."
} else {
    Write-Output "Synchronized header and booking modal across $($htmlFiles.Count) HTML files; updated $($changedFiles.Count)."
}
