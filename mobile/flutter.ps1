param([Parameter(ValueFromRemainingArguments=$true)][string[]]$FlutterArguments)
$ErrorActionPreference = 'Stop'
$taskFlutter = Join-Path $PSScriptRoot '..\.mobile-tools\flutter\bin\flutter.bat'
if (!(Test-Path -LiteralPath $taskFlutter)) {
    throw 'SDK Flutter non trovato. Installa Flutter oppure ripristina .mobile-tools/flutter.'
}
Push-Location $PSScriptRoot
try {
    & $taskFlutter @FlutterArguments
    if ($LASTEXITCODE -ne 0) { throw "Flutter ha restituito il codice $LASTEXITCODE." }
} finally { Pop-Location }
