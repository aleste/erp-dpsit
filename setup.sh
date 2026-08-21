#!/bin/bash
# ==============================================================================
# Script de Inicialización y Despliegue en Linux (Ubuntu / Debian / Docker)
# ERP DPSIT - Dirección Provincial Rectora de Tecnología
# ==============================================================================

set -e

# Colores para salida de terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # Sin color

echo -e "${BLUE}==================================================================${NC}"
echo -e "${BLUE}    🚀 ERP DPSIT - Inicialización de Entorno en Linux            ${NC}"
echo -e "${BLUE}==================================================================${NC}"

# 1. Verificar herramientas necesarias
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Error: Docker no está instalado o no se encuentra en el PATH.${NC}"
    echo "Por favor instale Docker: https://docs.docker.com/engine/install/ubuntu/"
    exit 1
fi

# Detectar comando de docker compose (v2 vs v1)
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo -e "${RED}❌ Error: Docker Compose no está instalado.${NC}"
    echo "Por favor instale docker compose: sudo apt-get install docker-compose-plugin"
    exit 1
fi

# 2. Configurar variables de entorno
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚙️  Creando archivo de variables de entorno (.env a partir de .env.example)...${NC}"
    cp .env.example .env
fi

if [ ! -f backend/.env ]; then
    cp .env backend/.env
fi

# 3. Permisos de carpetas en Linux
echo -e "${YELLOW}🔒 Ajustando permisos de carpetas de almacenamiento y logs...${NC}"
mkdir -p backend/storage/framework/cache/data
mkdir -p backend/storage/framework/sessions
mkdir -p backend/storage/framework/views
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || true

# 4. Levantar Contenedores Docker
echo -e "${BLUE}🐳 Construyendo e iniciando contenedores con ${DOCKER_COMPOSE}...${NC}"
$DOCKER_COMPOSE up -d --build

# 5. Esperar que PostgreSQL esté listo
echo -e "${YELLOW}⏳ Esperando que el motor PostgreSQL esté listo para aceptar conexiones...${NC}"
until $DOCKER_COMPOSE exec -T postgres pg_isready -U postgres -d erp_dpsit &> /dev/null; do
    sleep 2
done
echo -e "${GREEN}✓ PostgreSQL conectado.${NC}"

# 6. Instalar dependencias de backend Laravel
echo -e "${YELLOW}📦 Instalando dependencias de Composer en el Backend...${NC}"
$DOCKER_COMPOSE exec -T backend composer install --no-interaction --prefer-dist --optimize-autoloader

# 7. Configuración de Laravel y base de datos
echo -e "${YELLOW}🔑 Verificando clave de aplicación y ejecutando migraciones...${NC}"
$DOCKER_COMPOSE exec -T backend php artisan package:discover
$DOCKER_COMPOSE exec -T backend php artisan key:generate --force
$DOCKER_COMPOSE exec -T backend php artisan storage:link 2>/dev/null || true
$DOCKER_COMPOSE exec -T backend php artisan migrate:fresh --seed --force

# 8. Limpieza de caché
$DOCKER_COMPOSE exec -T backend php artisan optimize:clear
$DOCKER_COMPOSE exec -T backend php artisan package:discover

echo -e "\n${GREEN}==================================================================${NC}"
echo -e "${GREEN} ✅ Sistema ERP DPSIT desplegado exitosamente en Linux!           ${NC}"
echo -e "${GREEN}==================================================================${NC}"
echo ""
echo -e " 🌐 ${BLUE}Frontend Nuxt 3:${NC}  http://localhost:3000"
echo -e " 🔌 ${BLUE}Backend API:${NC}      http://localhost:8000/api"
echo -e " 🗄️  ${BLUE}PostgreSQL:${NC}       localhost:5432 (DB: erp_dpsit)"
echo ""
echo -e " 🔑 ${YELLOW}Credenciales de Acceso Inicial:${NC}"
echo -e "    • ${GREEN}Administrador:${NC} admin@dpsit.gob.ar  | password: admin123"
echo -e "    • ${GREEN}Editor:${NC}        editor@dpsit.gob.ar | password: editor123"
echo -e "    • ${GREEN}Lector:${NC}        lector@dpsit.gob.ar | password: lector123"
echo ""
echo -e " 💡 ${BLUE}Comandos útiles:${NC}"
echo -e "    • Iniciar sistema:    ${YELLOW}./start.sh${NC} (o ${YELLOW}make up${NC})"
echo -e "    • Detener sistema:    ${YELLOW}./stop.sh${NC}  (o ${YELLOW}make down${NC})"
echo -e "    • Recargar variables: ${YELLOW}./sync-env.sh${NC} (o ${YELLOW}make sync-env${NC})"
echo -e "    • Ver logs en vivo:   ${YELLOW}make logs${NC}"
echo -e "${GREEN}==================================================================${NC}"
