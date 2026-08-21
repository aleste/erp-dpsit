# ==============================================================================
# Detener Stack ERP DPSIT en WSLC
# ==============================================================================

Write-Host "[*] Deteniendo y removiendo contenedores en WSLC..." -ForegroundColor Yellow
wslc stop erp_frontend erp_webserver erp_backend erp_postgres 2>$null
wslc remove erp_frontend erp_webserver erp_backend erp_postgres 2>$null
Write-Host "[OK] Contenedores detenidos y removidos." -ForegroundColor Green
