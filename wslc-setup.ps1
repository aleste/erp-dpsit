# ==============================================================================
# Script de Despliegue Automatizado con WSLC (Windows Subsystem for Linux Containers)
# ERP DPSIT - Entorno de Gobernanza TI Provincial
#
# Uso:
#   .\wslc-setup.ps1          Levanta el stack CONSERVANDO los datos (migrate + seed solo si la base esta vacia)
#   .\wslc-setup.ps1 -Reset   BORRA y recrea la base de datos con datos demo (pide confirmacion)
# ==============================================================================

param(
    [switch]$Reset
)

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Iniciando ERP DPSIT con WSLC (WSL Containers)" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 0. Confirmacion explicita antes de borrar la base de datos
if ($Reset) {
    Write-Host "[ATENCION] -Reset BORRARA TODOS LOS DATOS de la base erp_dpsit (migrate:fresh --seed)." -ForegroundColor Red
    $confirmacion = Read-Host "Escriba BORRAR para continuar"
    if ($confirmacion -ne "BORRAR") {
        Write-Host "[*] Operacion cancelada. No se modifico nada." -ForegroundColor Yellow
        exit 1
    }
}

# 1. Comprobar version de WSLC
$wslcVersion = wslc --version 2>$null
if (-not $wslcVersion) {
    Write-Host "[ERROR] No se detecto 'wslc'. Por favor verifique su instalacion." -ForegroundColor Red
    exit 1
}
Write-Host "[OK] Version de WSLC detectada: $wslcVersion" -ForegroundColor Green

# 2. Sincronizar archivo .env principal a frontend y backend
if (Test-Path .env) {
    Copy-Item .env backend/.env -Force
    Copy-Item .env frontend/.env -Force
    Write-Host "[OK] Archivo .env sincronizado a backend/.env y frontend/.env" -ForegroundColor Green
}

# 3. Detener contenedores existentes si ya estan corriendo (los volumenes y datos se conservan)
Write-Host "[*] Limpiando contenedores previos..." -ForegroundColor Yellow
wslc stop erp_scheduler erp_frontend erp_webserver erp_backend erp_mailpit erp_postgres 2>$null
wslc remove erp_scheduler erp_frontend erp_webserver erp_backend erp_mailpit erp_postgres 2>$null

# 4. Crear Red y Volumen en WSLC si no existen
Write-Host "[*] Configurando Red y Volumen en WSLC..." -ForegroundColor Yellow
wslc network create erp_network 2>$null
wslc volume create postgres_data 2>$null

$baseDir = (Get-Location).Path -replace '\\', '/'

# 5. Construir Imagenes
Write-Host "[*] Construyendo imagen Backend (PHP 8.3 FPM + PostgreSQL)..." -ForegroundColor Yellow
wslc build -t erp_backend -f docker/php/Dockerfile .

Write-Host "[*] Construyendo imagen Frontend (Nuxt 3 + Vite)..." -ForegroundColor Yellow
wslc build -t erp_frontend -f docker/node/Dockerfile .

# 6. Iniciar PostgreSQL
Write-Host "[*] Iniciando Base de Datos PostgreSQL 16..." -ForegroundColor Yellow
wslc run -d --name erp_postgres --network erp_network --network-alias postgres -p 5432:5432 -e POSTGRES_DB=erp_dpsit -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=secret_dpsit_2026 -v postgres_data:/var/lib/postgresql/data postgres:16-alpine

# 7. Iniciar Mailpit (captura todos los correos de desarrollo; NO los reenvia). Buzon: http://localhost:8025
Write-Host "[*] Iniciando Mailpit (correo de desarrollo)..." -ForegroundColor Yellow
wslc run -d --name erp_mailpit --network erp_network --network-alias mailpit -p 8025:8025 axllent/mailpit:v1.31.2

# 8. Iniciar Backend PHP-FPM
Write-Host "[*] Iniciando Backend PHP 8.3 FPM..." -ForegroundColor Yellow
wslc run -d --name erp_backend --network erp_network --network-alias backend -w /var/www/backend -v "${baseDir}/backend:/var/www/backend" --env-file "${baseDir}/.env" erp_backend

# 9. Iniciar Servidor Web Nginx
Write-Host "[*] Iniciando Servidor Web Nginx (Proxy Reverso)..." -ForegroundColor Yellow
wslc run -d --name erp_webserver --network erp_network -p 8000:80 -v "${baseDir}/backend:/var/www/backend" -v "${baseDir}/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf" nginx:alpine

# 10. Iniciar Frontend Nuxt 3
Write-Host "[*] Iniciando Frontend Nuxt 3..." -ForegroundColor Yellow
wslc run -d --name erp_frontend --network erp_network -p 3000:3000 -w /app -v "${baseDir}/frontend:/app" --env-file "${baseDir}/.env" -e NODE_ENV=development -e NUXT_PUBLIC_API_BASE=http://localhost:8000/api -e CHOKIDAR_USEPOLLING=true erp_frontend

# 11. Esperar a que PostgreSQL acepte conexiones (maximo 60 segundos)
Write-Host "[*] Esperando que PostgreSQL acepte conexiones..." -ForegroundColor Yellow
$dbLista = $false
for ($i = 0; $i -lt 30; $i++) {
    wslc exec erp_postgres pg_isready -U postgres -d erp_dpsit *> $null
    if ($LASTEXITCODE -eq 0) { $dbLista = $true; break }
    Start-Sleep -Seconds 2
}
if (-not $dbLista) {
    Write-Host "[ERROR] PostgreSQL no respondio a tiempo. Revise: wslc logs erp_postgres" -ForegroundColor Red
    exit 1
}
Write-Host "[OK] PostgreSQL disponible." -ForegroundColor Green

Write-Host "[*] Instalando dependencias de Composer..." -ForegroundColor Yellow
wslc exec erp_backend composer install --no-interaction --prefer-dist --optimize-autoloader

# 12. Base de datos: por defecto se CONSERVAN los datos
if ($Reset) {
    Write-Host "[*] Recreando la base de datos con datos demo (migrate:fresh --seed)..." -ForegroundColor Red
    wslc exec erp_backend php artisan migrate:fresh --seed --force
} else {
    Write-Host "[*] Aplicando migraciones pendientes (los datos existentes se conservan)..." -ForegroundColor Yellow
    wslc exec erp_backend php artisan migrate --force

    # Datos demo solo si la base esta vacia (primer arranque)
    $salida = wslc exec erp_backend php artisan tinker --execute="echo App\Models\User::count();" 2>$null
    $usuarios = ($salida | Select-Object -Last 1)
    if ("$usuarios".Trim() -eq "0") {
        Write-Host "[*] Base de datos vacia: cargando datos demo..." -ForegroundColor Yellow
        wslc exec erp_backend php artisan db:seed --force
    } else {
        Write-Host "[OK] La base ya contiene datos; no se ejecutan seeders." -ForegroundColor Green
    }
}

# 13. Iniciar el Scheduler de Laravel (tareas programadas: recordatorios de firma digital)
Write-Host "[*] Iniciando Scheduler de Laravel (tareas programadas)..." -ForegroundColor Yellow
wslc run -d --name erp_scheduler --network erp_network -w /var/www/backend -v "${baseDir}/backend:/var/www/backend" --env-file "${baseDir}/.env" erp_backend php artisan schedule:work

Write-Host "==========================================================" -ForegroundColor Green
Write-Host " [OK] Despliegue con WSLC completado exitosamente!" -ForegroundColor Green
Write-Host ""
Write-Host " - Frontend Nuxt 3:  http://localhost:3000" -ForegroundColor Cyan
Write-Host " - Backend Laravel:   http://localhost:8000/api" -ForegroundColor Cyan
Write-Host " - PostgreSQL:        localhost:5432 (DB: erp_dpsit)" -ForegroundColor Cyan
Write-Host " - Mailpit (correo):  http://localhost:8025" -ForegroundColor Cyan
Write-Host ""
Write-Host " Credenciales Demo:" -ForegroundColor Yellow
Write-Host "    - Admin:  admin@dpsit.gob.ar  / admin123"
Write-Host "    - Editor: editor@dpsit.gob.ar / editor123"
Write-Host "    - Lector: lector@dpsit.gob.ar / lector123"
Write-Host "==========================================================" -ForegroundColor Green
