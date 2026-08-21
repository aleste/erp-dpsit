#!/bin/bash
# ==============================================================================
# Script para Iniciar el Entorno ERP DPSIT en Linux
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

echo "🚀 Iniciando contenedores de ERP DPSIT..."
$DOCKER_COMPOSE up -d

echo "✅ Contenedores activos:"
echo "   - Frontend: http://localhost:3000"
echo "   - Backend:  http://localhost:8000/api"
