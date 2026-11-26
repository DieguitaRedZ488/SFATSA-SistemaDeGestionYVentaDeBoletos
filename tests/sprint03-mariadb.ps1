param(
    [string]$MariaDbBin = 'C:\Program Files\MariaDB 12.0\bin',
    [string]$Php = 'C:\xampp\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$testId = [guid]::NewGuid().ToString('N')
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('sfatsa-s03-' + $testId)
$testData = Join-Path $testRoot 'data'
$testPipe = 'SFATSA_S03_' + $testId
$testServer = $null
$testExit = 1
try {
    New-Item -ItemType Directory -Path $testRoot | Out-Null
    Set-Content -LiteralPath (Join-Path $testRoot 'identidad.txt') -Value $testId
    & (Join-Path $MariaDbBin 'mariadb-install-db.exe') ('--datadir=' + $testData) --skip-networking ('--socket=' + $testPipe) --silent
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo inicializar la instancia aislada.' }
    $testArgs = @('--no-defaults', ('--datadir="' + $testData + '"'), '--skip-networking', '--enable-named-pipe', ('--socket=' + $testPipe), '--skip-log-bin', '--innodb-buffer-pool-size=32M', '--console')
    $testServer = Start-Process -FilePath (Join-Path $MariaDbBin 'mariadbd.exe') -ArgumentList $testArgs -WindowStyle Hidden -RedirectStandardOutput (Join-Path $testRoot 'server-out.txt') -RedirectStandardError (Join-Path $testRoot 'server-err.txt') -PassThru
    $testReady = $false
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        if ($testServer.HasExited) { throw 'El servidor de ensayo no pudo iniciar.' }
        if ((Get-Content -LiteralPath (Join-Path $testRoot 'server-err.txt') -Raw) -match 'ready for connections') { $testReady = $true; break }
        Start-Sleep -Milliseconds 250
    }
    if (-not $testReady) { throw 'El servidor de ensayo no respondió.' }
    $testConfig = @{ id = $testId; datadir = $testData; pipe = $testPipe } | ConvertTo-Json -Compress
    $testConfig | & $Php (Join-Path $PSScriptRoot 'sprint03-mariadb.php')
    $testExit = $LASTEXITCODE
} finally {
    if ($null -ne $testServer -and -not $testServer.HasExited) {
        $identity = & (Join-Path $MariaDbBin 'mariadb.exe') --no-defaults --protocol=PIPE ('--socket=' + $testPipe) -u root --batch --skip-column-names '--execute=SELECT @@socket,@@skip_networking'
        if ($LASTEXITCODE -ne 0 -or $identity -ne ($testPipe + "`t1")) { throw 'No se pudo verificar la instancia para apagarla; se conserva el temporal.' }
        & (Join-Path $MariaDbBin 'mariadb.exe') --no-defaults --protocol=PIPE ('--socket=' + $testPipe) -u root --execute=SHUTDOWN
        if ($LASTEXITCODE -ne 0 -or -not $testServer.WaitForExit(10000)) { throw 'La instancia no confirmó el apagado; se conserva el temporal.' }
    }
    # Solo el directorio creado en esta ejecución, tras verificar ruta e identidad.
    if (Test-Path -LiteralPath $testRoot) {
        $resolved = (Resolve-Path -LiteralPath $testRoot).Path
        $expected = [IO.Path]::GetFullPath((Join-Path ([IO.Path]::GetTempPath()) ('sfatsa-s03-' + $testId)))
        if ($resolved -ne $expected -or (Get-Content -LiteralPath (Join-Path $resolved 'identidad.txt') -Raw).Trim() -ne $testId) { throw 'Ruta temporal no permitida para limpieza.' }
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
exit $testExit
