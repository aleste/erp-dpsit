# ERP DPSIT - Dirección Provincial de Sistemas de Información y Tecnología

Sistema ERP Integral para la gobernanza, rectoría y gestión transversal de tecnologías de la información a nivel provincial.

> 📚 **Documentación técnica y estado del proyecto:** ver [`docs/README.md`](docs/README.md).
> Instrucciones para agentes de IA y colaboradores: [`AGENTS.md`](AGENTS.md).
> `wslc-setup.ps1` y `setup.sh` conservan los datos; solo `-Reset` / `--reset` recrean la base.
> Correo de desarrollo: Mailpit en http://localhost:8025 (captura todo, no envía a internet).

---

## 🏛️ Arquitectura del Sistema

- **Base de Datos**: PostgreSQL 16 (Relacional con soporte JSONB, transacciones e indexación).
- **Backend API**: Laravel 11 (PHP 8.3 FPM, Sanctum, Eloquent ORM, Migraciones, Seeders, Scopes de Vigencia y RBAC).
- **Frontend SPA**: Nuxt 3 / Vue 3 (Composition API `<script setup>`, Pinia Store, Tailwind CSS, Toasts y Modales).
- **Entornos Compatibles**:
  - **Linux (Ubuntu / Debian)**: Docker Engine + Docker Compose v2.
  - **Windows**: WSLC (WSL Container CLI `v2.9+`) o Docker Desktop.

---

## 🐧 Ejecución en Linux (Ubuntu / Debian)

### 1. Requisitos Previos en Ubuntu

Asegurate de tener instalados **Docker** y el plugin **Docker Compose v2**:

```bash
# Instalar Docker en Ubuntu (si aún no está instalado)
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-plugin git curl make

# Permitir ejecutar Docker sin sudo (opcional pero recomendado)
sudo usermod -aG docker $USER
# (Luego cerrá sesión y volvé a entrar o ejecutá: newgrp docker)
```

### 2. Clonar el Repositorio y Desplegar (Paso a Paso)

```bash
# 1. Clonar el repositorio
git clone <URL_DE_TU_REPOSITORIO_GITHUB> erp-dpsit
cd erp-dpsit

# 2. Dar permisos de ejecución a los scripts
chmod +x setup.sh start.sh stop.sh sync-env.sh

# 3. Ejecutar la inicialización completa automatizada
./setup.sh
# (O mediante make: make setup)
```

El script `./setup.sh`:
1. Crea el `.env` a partir de `.env.example`.
2. Ajusta permisos de las carpetas `storage` y `bootstrap/cache`.
3. Construye y levanta los contenedores con Docker Compose.
4. Espera a que PostgreSQL esté disponible.
5. Instala las dependencias con Composer en el contenedor Backend.
6. Ejecuta las migraciones y seeders (`migrate:fresh --seed`).
7. Configura el frontend Nuxt 3 e inicia el servidor en desarrollo.

### 3. Comandos de Uso Diario en Linux

Podés usar los scripts directos o el `Makefile`:

| Acción | Con Script Bash | Con `make` |
| :--- | :--- | :--- |
| **Iniciar sistema** | `./start.sh` | `make up` |
| **Detener sistema** | `./stop.sh` | `make down` |
| **Reiniciar sistema** | `docker compose restart` | `make restart` |
| **Ver logs en vivo** | `docker compose logs -f` | `make logs` |
| **Recargar variables `.env`** | `./sync-env.sh` | `make sync-env` |
| **Recrear base de datos limpia** | — | `make fresh` |
| **Terminal Backend** | `docker compose exec backend sh` | `make shell-backend` |
| **Terminal Frontend** | `docker compose exec frontend sh` | `make shell-frontend` |
| **Terminal PostgreSQL** | `docker compose exec postgres psql -U postgres -d erp_dpsit` | `make shell-db` |

---

## 🪟 Ejecución en Windows (WSLC / PowerShell)

En la máquina con Windows, podés continuar trabajando utilizando PowerShell:

```powershell
# Levantar el stack completo con WSLC (conserva los datos):
.\wslc-setup.ps1

# Recrear la base con datos demo (borra todo, pide confirmación):
.\wslc-setup.ps1 -Reset

# Detener los contenedores:
.\wslc-down.ps1

# Recargar variables de entorno tras editar .env:
.\sync-env.ps1
```

---

## 🔄 Flujo de Trabajo al Alternar entre Ordenadores (Git)

Al pasar de una computadora a otra:

### En la computadora donde terminás de trabajar:
```bash
git add .
git commit -m "feat: descripción de tus cambios"
git push origin main
```

### En la otra computadora (antes de comenzar):
```bash
git pull origin main

# Si hubo cambios en migraciones (conserva los datos):
make migrate        # En Linux
# o: wslc exec erp_backend php artisan migrate --force  # En Windows
# (make fresh / wslc-setup.ps1 -Reset BORRAN la base: solo si se aceptan perder los datos locales)

# Si cambiaste variables de entorno:
./sync-env.sh       # En Linux
# o: .\sync-env.ps1 # En Windows
```

---

## 🌐 URLs y Servicios Expuestos

| Servicio | URL | Descripción |
| :--- | :--- | :--- |
| **Frontend Nuxt 3** | [http://localhost:3000](http://localhost:3000) | Interfaz de Usuario y Gestión Integral |
| **Backend API** | [http://localhost:8000/api](http://localhost:8000/api) | API RESTful protegida con Laravel Sanctum |
| **Mailpit (correo de desarrollo)** | [http://localhost:8025](http://localhost:8025) | Buzón donde quedan capturados los correos (no se envían a internet) |
| **PostgreSQL** | `localhost:5432` | DB: `erp_dpsit` / User: `postgres` / Pass: `secret_dpsit_2026` |

---

## 🔑 Credenciales de Acceso (Perfiles RBAC)

| Perfil | Email | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@dpsit.gob.ar` | `admin123` | Control total, gestión de usuarios, auditoría y borrado físico |
| **Editor** | `editor@dpsit.gob.ar` | `editor123` | Alta, baja y modificación de áreas, servicios, personas, roles y actas |
| **Lector** | `lector@dpsit.gob.ar` | `lector123` | Solo lectura, consultas cruzadas, vista previa y exportación CSV |
