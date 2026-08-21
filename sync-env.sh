#!/bin/bash
# ==============================================================================
# Script de Sincronización y Recarga de Variables de Entorno en Linux
# ==============================================================================

# Detectar comando de docker compose
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo "❌ Error: Docker Compose no encontrado."
    exit 1
fi

echo "🔄 Sincronizando archivo .env con backend..."
cp .env backend/.env 2>/dev/null || true

echo "🧹 Limpiando y reconstruyendo caché de configuración de Laravel..."
$DOCKER_COMPOSE exec -T backend php artisan optimize:clear
$DOCKER_COMPOSE exec -T backend php artisan package:discover

echo "♻️  Reiniciando servicios frontend y backend para aplicar cambios..."
$DOCKER_COMPOSE restart backend webserver frontend

echo "✅ Variables de entorno sincronizadas y recargadas correctamente!"
echo "   - Frontend: http://localhost:3000"
echo "   - Backend:  http://localhost:8000/api"
