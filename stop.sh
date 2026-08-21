#!/bin/bash
# ==============================================================================
# Script para Detener el Entorno ERP DPSIT en Linux
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

echo "🛑 Deteniendo contenedores de ERP DPSIT..."
$DOCKER_COMPOSE down

echo "✓ Entorno detenido."
