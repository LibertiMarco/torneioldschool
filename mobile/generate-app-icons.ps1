$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$taskSource = [System.Drawing.Bitmap]::new((Join-Path $PSScriptRoot '../img/logo_old_school_1200.png'))

function Write-AppIcon([string]$Path, [int]$Size, [double]$Fraction = 1, [bool]$Transparent = $false) {
    $taskDirectory = Split-Path -Parent $Path
    New-Item -ItemType Directory -Force -Path $taskDirectory | Out-Null
    $taskIcon = [System.Drawing.Bitmap]::new($Size, $Size)
    $taskGraphics = [System.Drawing.Graphics]::FromImage($taskIcon)
    try {
        if ($Transparent) { $taskGraphics.Clear([System.Drawing.Color]::Transparent) }
        else { $taskGraphics.Clear([System.Drawing.Color]::White) }
        $taskGraphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $taskGraphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $taskSide = [int][Math]::Round($Size * $Fraction)
        $taskOffset = [int][Math]::Round(($Size - $taskSide) / 2)
        $taskGraphics.DrawImage($taskSource, $taskOffset, $taskOffset, $taskSide, $taskSide)
        $taskIcon.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    } finally {
        $taskGraphics.Dispose()
        $taskIcon.Dispose()
    }
}

try {
    $taskDensities = @{ mdpi = 48; hdpi = 72; xhdpi = 96; xxhdpi = 144; xxxhdpi = 192 }
    foreach ($taskDensity in $taskDensities.Keys) {
        Write-AppIcon (Join-Path $PSScriptRoot "android/app/src/main/res/mipmap-$taskDensity/ic_launcher.png") $taskDensities[$taskDensity]
        # The complete logo stays inside the adaptive icon's 66dp safe area.
        $taskAdaptiveSize = [int]($taskDensities[$taskDensity] * 108 / 48)
        Write-AppIcon (Join-Path $PSScriptRoot "android/app/src/main/res/drawable-$taskDensity/ic_launcher_foreground.png") $taskAdaptiveSize (66.0 / 108.0) $true
    }
    $taskIosDirectory = Join-Path $PSScriptRoot 'ios/Runner/Assets.xcassets/AppIcon.appiconset'
    $taskContents = Get-Content (Join-Path $taskIosDirectory 'Contents.json') -Raw | ConvertFrom-Json
    foreach ($taskEntry in $taskContents.images) {
        $taskPoints = [double]::Parse(($taskEntry.size -split 'x')[0], [System.Globalization.CultureInfo]::InvariantCulture)
        $taskScale = [int]($taskEntry.scale -replace 'x', '')
        Write-AppIcon (Join-Path $taskIosDirectory $taskEntry.filename) ([int]($taskPoints * $taskScale))
    }
} finally { $taskSource.Dispose() }
