# ==============================================================================
# Script de Despliegue Automatizado con WSLC (Windows Subsystem for Linux Containers)
# ERP DPSIT - Entorno de Gobernanza TI Provincial
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Iniciando ERP DPSIT con WSLC (WSL Containers)" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

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

# 3. Detener contenedores existentes si ya estan corriendo
Write-Host "[*] Limpiando contenedores previos..." -ForegroundColor Yellow
wslc stop erp_frontend erp_webserver erp_backend erp_postgres 2>$null
wslc remove erp_frontend erp_webserver erp_backend erp_postgres 2>$null

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
wslc run -d --name erp_postgres --network erp_network -p 5432:5432 -e POSTGRES_DB=erp_dpsit -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=secret_dpsit_2026 -v postgres_data:/var/lib/postgresql/data postgres:16-alpine

# 7. Iniciar Backend PHP-FPM
Write-Host "[*] Iniciando Backend PHP 8.3 FPM..." -ForegroundColor Yellow
wslc run -d --name erp_backend --network erp_network -w /var/www/backend -v "${baseDir}/backend:/var/www/backend" --env-file "${baseDir}/.env" erp_backend

# 8. Iniciar Servidor Web Nginx
Write-Host "[*] Iniciando Servidor Web Nginx (Proxy Reverso)..." -ForegroundColor Yellow
wslc run -d --name erp_webserver --network erp_network -p 8000:80 -v "${baseDir}/backend:/var/www/backend" -v "${baseDir}/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf" nginx:alpine

# 9. Iniciar Frontend Nuxt 3
Write-Host "[*] Iniciando Frontend Nuxt 3..." -ForegroundColor Yellow
wslc run -d --name erp_frontend --network erp_network -p 3000:3000 -w /app -v "${baseDir}/frontend:/app" --env-file "${baseDir}/.env" -e NODE_ENV=development -e NUXT_PUBLIC_API_BASE=http://localhost:8000/api -e CHOKIDAR_USEPOLLING=true erp_frontend

# 10. Esperar inicializacion de base de datos
Write-Host "[*] Esperando 6 segundos para inicializacion de servicios..." -ForegroundColor Yellow
Start-Sleep -Seconds 6

Write-Host "[*] Instalando dependencias de Composer..." -ForegroundColor Yellow
wslc exec erp_backend composer install --no-interaction --prefer-dist --optimize-autoloader

Write-Host "[*] Ejecutando migraciones y seeders de gobierno provincial..." -ForegroundColor Yellow
wslc exec erp_backend php artisan migrate:fresh --seed --force

Write-Host "==========================================================" -ForegroundColor Green
Write-Host " [OK] Despliegue con WSLC completado exitosamente!" -ForegroundColor Green
Write-Host ""
Write-Host " - Frontend Nuxt 3:  http://localhost:3000" -ForegroundColor Cyan
Write-Host " - Backend Laravel:   http://localhost:8000/api" -ForegroundColor Cyan
Write-Host " - PostgreSQL:        localhost:5432 (DB: erp_dpsit)" -ForegroundColor Cyan
Write-Host ""
Write-Host " Credenciales Demo:" -ForegroundColor Yellow
Write-Host "    - Admin:  admin@dpsit.gob.ar  / admin123"
Write-Host "    - Editor: editor@dpsit.gob.ar / editor123"
Write-Host "    - Lector: lector@dpsit.gob.ar / lector123"
Write-Host "==========================================================" -ForegroundColor Green
