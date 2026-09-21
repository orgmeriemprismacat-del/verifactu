param(
    [ValidateSet('Test','Migrate','Preflight','GoNoGo','Start','Stop')]
    [string]$Action = 'Test'
)
$ErrorActionPreference = 'Stop'
$repo = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$runtime = Join-Path $repo 'sif/var/runtime'
$php = Join-Path $runtime 'php/php.exe'
$mysqlBin = Join-Path $runtime 'mysql-8.4.10-winx64/bin'
$envFile = Join-Path $repo 'sif/var/test-env.json'
if ($Action -eq 'Start') {
    Start-Process -FilePath (Join-Path $mysqlBin 'mysqld.exe') -ArgumentList ('--defaults-file="' + (Join-Path $runtime 'my.ini') + '"') -WindowStyle Hidden
    return
}
if ($Action -eq 'Stop') {
    & (Join-Path $mysqlBin 'mysqladmin.exe') ('--defaults-extra-file=' + (Join-Path $runtime 'admin.ini')) shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Could not stop the isolated MySQL server.' }
    return
}
if (!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $envFile)) {
    throw 'Local runtime/configuration missing. See sif/tests/README.md.'
}
$values = Get-Content -LiteralPath $envFile -Raw | ConvertFrom-Json
$previous = @{}
try {
    foreach ($property in $values.PSObject.Properties) {
        $previous[$property.Name] = [Environment]::GetEnvironmentVariable($property.Name, 'Process')
        [Environment]::SetEnvironmentVariable($property.Name, $property.Value, 'Process')
    }
    $relative = switch ($Action) {
        'Test' { 'sif/tests/run-tests.php' }
        'Migrate' { 'sif/scripts/run-migrations.php' }
        'Preflight' { 'sif/scripts/preflight-sif.php' }
        'GoNoGo' { 'sif/scripts/go-no-go-preproduction.php' }
    }
    & $php (Join-Path $repo $relative)
    $result = $LASTEXITCODE
} finally {
    foreach ($name in $previous.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process')
    }
}
exit $result
