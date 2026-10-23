# ==============================================================================
# Detener Stack ERP DPSIT en WSLC (el volumen postgres_data se conserva)
# ==============================================================================

Write-Host "[*] Deteniendo y removiendo contenedores en WSLC..." -ForegroundColor Yellow
wslc stop erp_scheduler erp_frontend erp_webserver erp_backend erp_mailpit erp_postgres 2>$null
wslc remove erp_scheduler erp_frontend erp_webserver erp_backend erp_mailpit erp_postgres 2>$null
Write-Host "[OK] Contenedores detenidos y removidos." -ForegroundColor Green
