$ErrorActionPreference = 'Stop'
$Project = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Project

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'Falta PHP 8.3 o posterior. Instálalo y vuelve a abrir PowerShell.'
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Falta Composer. Instálalo y vuelve a abrir PowerShell.'
}
$PhpVersion = [version]((& php -r 'echo PHP_VERSION;').Trim())
if ($PhpVersion -lt [version]'8.3') {
    throw "LabKeep requiere PHP 8.3 o posterior. Se encontró $PhpVersion."
}
if (-not (Test-Path 'vendor/autoload.php')) {
    composer install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Composer no pudo instalar las dependencias.' }
}
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
}
if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=base64:.+' -Quiet)) {
    php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la clave local.' }
}
if (-not (Test-Path 'database/database.sqlite')) {
    New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null
}
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar las tablas.' }

php artisan labkeep:check-admin --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host 'Todavía no hay un administrador activo. Configúralo ahora.' -ForegroundColor Yellow
    $Username = Read-Host 'Nombre de usuario'
    $Email = Read-Host 'Correo de contacto'
    php artisan labkeep:admin $Username "--email=$Email" --reset
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el administrador.' }
}
Write-Host ''
Write-Host 'LabKeep está disponible en http://127.0.0.1:8000' -ForegroundColor Green
Write-Host 'Deja esta ventana abierta mientras lo uses. Para detener el servidor, presiona Ctrl+C.'
php artisan serve --host=127.0.0.1 --port=8000

