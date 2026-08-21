# ==============================================================================
# Makefile para Gestión del ERP DPSIT (Linux / Ubuntu / Docker)
# ==============================================================================

.PHONY: setup up start down stop restart logs sync-env migrate fresh seed discover clear shell-backend shell-frontend shell-db test help

help:
	@echo "Comandos disponibles:"
	@echo "  make setup          - Configuración inicial completa (construye, migra y puebla la BD)"
	@echo "  make up             - Inicia todos los contenedores en segundo plano"
	@echo "  make down           - Detiene todos los contenedores"
	@echo "  make restart        - Reinicia todos los contenedores"
	@echo "  make logs           - Muestra los logs en tiempo real"
	@echo "  make sync-env       - Recarga variables de entorno y limpia cachés"
	@echo "  make fresh          - Ejecuta migrate:fresh --seed (recrea la base de datos limpia)"
	@echo "  make migrate        - Ejecuta migraciones pendientes"
	@echo "  make seed           - Ejecuta los seeders"
	@echo "  make shell-backend  - Abre una terminal interactiva en el contenedor Backend"
	@echo "  make shell-frontend - Abre una terminal interactiva en el contenedor Frontend"
	@echo "  make shell-db       - Abre una terminal psql en PostgreSQL"

setup:
	@chmod +x setup.sh start.sh stop.sh sync-env.sh 2>/dev/null || true
	./setup.sh

up:
	docker compose up -d

start: up

down:
	docker compose down

stop: down

restart:
	docker compose restart

logs:
	docker compose logs -f

sync-env:
	@chmod +x sync-env.sh 2>/dev/null || true
	./sync-env.sh

migrate:
	docker compose exec backend php artisan migrate

seed:
	docker compose exec backend php artisan db:seed

fresh:
	docker compose exec backend php artisan migrate:fresh --seed --force

discover:
	docker compose exec backend php artisan package:discover

clear:
	docker compose exec backend php artisan optimize:clear
	docker compose exec backend php artisan package:discover

shell-backend:
	docker compose exec backend sh

shell-frontend:
	docker compose exec frontend sh

shell-db:
	docker compose exec postgres psql -U postgres -d erp_dpsit

test:
	docker compose exec backend php artisan test
