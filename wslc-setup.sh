#!/bin/bash
# ==============================================================================
# Script de Despliegue con WSLC (Bash)
# ==============================================================================

echo "=========================================================="
echo "    Iniciando ERP DPSIT con WSLC                          "
echo "=========================================================="

wslc version || { echo "Error: wslc no está disponible."; exit 1; }

# Crear red y volumen
wslc network create erp_network 2>/dev/null || true
wslc volume create postgres_data 2>/dev/null || true

# Construir imágenes
echo "🔨 Construyendo imágenes con wslc..."
wslc build -t erp_backend -f docker/php/Dockerfile .
wslc build -t erp_frontend -f docker/node/Dockerfile .

# Limpiar previos
wslc stop erp_postgres erp_backend erp_webserver erp_frontend 2>/dev/null || true
wslc remove erp_postgres erp_backend erp_webserver erp_frontend 2>/dev/null || true

# Iniciar PostgreSQL
echo "🗄️ Levantando PostgreSQL..."
wslc run -d \
    --name erp_postgres \
    --network erp_network \
    -p 5432:5432 \
    -e POSTGRES_DB=erp_dpsit \
    -e POSTGRES_USER=postgres \
    -e POSTGRES_PASSWORD=secret_dpsit_2026 \
    -v postgres_data:/var/lib/postgresql/data \
    postgres:16-alpine

# Iniciar Backend
echo "⚙️ Levantando Backend Laravel..."
wslc run -d \
    --name erp_backend \
    --network erp_network \
    -w /var/www/backend \
    -v "$(pwd)/backend:/var/www/backend" \
    -v "$(pwd)/docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini" \
    -e APP_ENV=local \
    -e APP_DEBUG=true \
    -e APP_KEY=base64:7K5Z8sKqY5w+0Hk3Q4X9l0gN6m1pB3rT8vU2wX5yZ0A= \
    -e DB_CONNECTION=pgsql \
    -e DB_HOST=erp_postgres \
    -e DB_PORT=5432 \
    -e DB_DATABASE=erp_dpsit \
    -e DB_USERNAME=postgres \
    -e DB_PASSWORD=secret_dpsit_2026 \
    erp_backend

# Iniciar Webserver Nginx
echo "🌐 Levantando Webserver Nginx..."
wslc run -d \
    --name erp_webserver \
    --network erp_network \
    -p 8000:80 \
    -v "$(pwd)/backend:/var/www/backend" \
    -v "$(pwd)/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf" \
    nginx:alpine

# Iniciar Frontend Nuxt
echo "✨ Levantando Frontend Nuxt 3..."
wslc run -d \
    --name erp_frontend \
    --network erp_network \
    -p 3000:3000 \
    -w /app \
    -v "$(pwd)/frontend:/app" \
    -e NODE_ENV=development \
    -e NUXT_PUBLIC_API_BASE=http://localhost:8000/api \
    -e CHOKIDAR_USEPOLLING=true \
    erp_frontend

sleep 5

echo "📦 Instalando Composer y corriendo migraciones..."
wslc exec erp_backend composer install --no-interaction --prefer-dist --optimize-autoloader
wslc exec erp_backend php artisan migrate:fresh --seed --force

echo "=========================================================="
echo " ✅ Sistema ERP DPSIT operativo con WSLC!"
echo " 🌐 Frontend Nuxt 3:  http://localhost:3000"
echo " 🔌 Backend Laravel:   http://localhost:8000/api"
echo "=========================================================="
