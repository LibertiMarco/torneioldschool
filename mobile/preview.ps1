param([switch]$Rebuild, [switch]$Demo)
$ErrorActionPreference = 'Stop'
if (!$Demo) {
    Write-Host 'Anteprima con account e dati reali: https://torneioldschool.it/app-preview/'
    Write-Host 'Accedi come admin del sito. Le modifiche confermate vengono salvate nel database reale.'
    return
}
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskFlutter = Join-Path $taskRoot '.mobile-tools\flutter\bin\flutter.bat'
$taskWeb = Join-Path $PSScriptRoot 'build\web'
$taskPhp = 'C:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $taskFlutter) -or !(Test-Path -LiteralPath $taskPhp)) {
    throw 'Servono Flutter locale e PHP XAMPP per avviare questa anteprima.'
}
Push-Location $PSScriptRoot
try {
    if ($Demo -or $Rebuild -or !(Test-Path -LiteralPath (Join-Path $taskWeb 'index.html'))) {
        & $taskFlutter build web --release --dart-define=TOS_PREVIEW=true --no-web-resources-cdn
        if ($LASTEXITCODE -ne 0) { throw 'Compilazione anteprima non riuscita.' }
    }
    $taskPort = Get-NetTCPConnection -LocalPort 8088 -State Listen -ErrorAction SilentlyContinue
    if (!$taskPort) {
        $taskServer = Start-Process -FilePath $taskPhp -ArgumentList @('-S', '127.0.0.1:8088', '-t', ('"' + $taskWeb + '"')) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskWeb 'preview-server.out.log') -RedirectStandardError (Join-Path $taskWeb 'preview-server.err.log')
        Write-Host "Server anteprima avviato (PID $($taskServer.Id))."
    } else {
        Write-Host 'La porta 8088 è già in uso. Se l’anteprima non appare, verificare il processo che la occupa.'
    }
    Write-Host 'Apri http://localhost:8088 sul PC. Dati dimostrativi: nessuna modifica al sito reale.'
} finally { Pop-Location }
