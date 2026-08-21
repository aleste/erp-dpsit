# ==============================================================================
# Script de Recarga Rápida de Variables de Entorno (ERP DPSIT)
# ==============================================================================

Write-Host "[*] Sincronizando archivo .env..." -ForegroundColor Yellow

if (Test-Path .env) {
    Copy-Item .env backend/.env -Force
    Copy-Item .env frontend/.env -Force
    Write-Host "[OK] Archivo .env copiado a backend/.env y frontend/.env" -ForegroundColor Green
} else {
    Write-Host "[ERROR] No se encontro el archivo .env principal." -ForegroundColor Red
    exit 1
}

# 1. Limpiar cache de configuracion de Laravel en el Backend
Write-Host "[*] Refrescando configuracion de Laravel..." -ForegroundColor Yellow
wslc exec erp_backend php artisan optimize:clear
wslc exec erp_backend php artisan package:discover

# 2. Reiniciar contenedor de Frontend para recargar variables en Nuxt
Write-Host "[*] Reiniciando contenedor de Frontend (Nuxt 3)..." -ForegroundColor Yellow
wslc stop erp_frontend
wslc start erp_frontend

Write-Host "==========================================================" -ForegroundColor Green
Write-Host " [OK] Variables de entorno actualizadas exitosamente!" -ForegroundColor Green
Write-Host " Los cambios ya estan vigentes en http://localhost:3000" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Green
