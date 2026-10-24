# Desarrollo: entorno, comandos y validación

## Requisitos

| Entorno | Necesario | Verificado en esta máquina (2026-10-23) |
| :--- | :--- | :--- |
| Windows (habitual) | WSLC ≥ 2.9 (`wslc --version`), PowerShell con ejecución de scripts habilitada | WSLC 3.0.3; host **sin** php, composer, docker ni make |
| Linux (Ubuntu) | Docker Engine + Compose v2, make | no verificado |

PHP 8.3.36, Laravel 11.55.1 y Composer corren **dentro** de `erp_backend`. Node 20 y Nuxt 3.21.11 corren
dentro de `erp_frontend`; el host también tiene Node 20.11.

## Variables de entorno

Archivo `.env` en la raíz, creado a partir de `.env.example`. Los scripts lo **copian** a `backend/.env`
y `frontend/.env`: editá solo el de la raíz y después ejecutá `sync-env.ps1` (Windows) o `sync-env.sh`
(Linux).

| Variable | Uso |
| :--- | :--- |
| `AGENCY_NAME`, `AGENCY_SHORT_NAME`, `AGENCY_ACRONYM`, `AGENCY_GOVERNMENT`, `AGENCY_TAGLINE` | Denominación institucional (backend `config('app.agency.*')`, frontend `useAgency()`). Con espacios, entre comillas; el frontend las quita |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_URL`, `FRONTEND_URL` | Laravel; `FRONTEND_URL` se agrega a los orígenes CORS |
| `FRONTEND_PORT`, `BACKEND_PORT`, `DB_PORT` | Puertos del host (Compose). `DB_PORT` aparece dos veces en `.env.example`: es la misma variable, sirve como puerto del host y del contenedor |
| `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a PostgreSQL (`DB_HOST=postgres`) |
| `FILESYSTEM_DISK` | `local` |
| `UID`, `GID` | Argumentos de build de la imagen PHP. El `Dockerfile` actual no los usa |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Correo. En desarrollo `smtp` a `mailpit:1025` (Mailpit captura y **no reenvía**). Nunca apuntar a un SMTP real en desarrollo |
| `MAILPIT_UI_PORT` | Puerto del buzón web de Mailpit en Compose (8025) |
| `FIRMA_VIGENCIA_MESES`, `FIRMA_RECORDATORIO_DIAS`, `FIRMA_RECORDATORIO_HORA`, `FIRMA_RECORDATORIO_MAX_INTENTOS` | Módulo de firma digital: 12 meses, 30 días, `08:00` y 3 intentos por defecto (`config/firma_digital.php`). Cambiarlos afecta solo a los registros nuevos o editados |
| `EXPEDIENTES_UMBRAL_DIAS` | Días en el área a partir de los cuales un expediente se marca como demorado (30) |
| `NUXT_PUBLIC_API_BASE` | URL de la API para la SPA. Compose y WSLC la fijan en `http://localhost:8000/api` |

No copies los valores reales a documentos ni a commits: `.env` está en `.gitignore`.

## Windows con WSLC

```powershell
# Una vez por sesión si la política es Restricted (no requiere administrador)
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

.\wslc-setup.ps1          # construye imágenes y (re)crea los 6 contenedores CONSERVANDO los datos
                          # (migrate + seed solo si la base está vacía)
.\wslc-setup.ps1 -Reset   # ⚠ recrea la base con datos demo (pide escribir BORRAR)
.\wslc-down.ps1    # detiene y elimina los contenedores (el volumen postgres_data se conserva)
.\sync-env.ps1     # copia .env, limpia cachés de Laravel y reinicia el frontend
wslc ps            # estado
wslc logs erp_backend
```

Desde el 2026-10-23 el script **conserva los datos** (DEF-02 resuelto). Contenedores:

| Contenedor | Uso |
| :--- | :--- |
| `erp_postgres` | base de datos (volumen `postgres_data`) |
| `erp_backend`, `erp_webserver` | API (PHP-FPM + nginx en :8000) |
| `erp_frontend` | SPA en :3000 |
| `erp_mailpit` | correo de desarrollo; buzón en http://localhost:8025 (API: `/api/v1/messages`) |
| `erp_scheduler` | `php artisan schedule:work`: ejecuta las tareas de `routes/console.php` |

Las variables pasadas con `--env-file` quedan fijas al **crear** el contenedor: para que un cambio en `.env`
llegue a `erp_backend` o `erp_scheduler`, hay que recrearlos con `wslc-setup.ps1`, que conserva los datos.
`sync-env.ps1` solo limpia cachés y reinicia el frontend (DEF-17).

Comandos habituales del backend:

```powershell
wslc exec erp_backend php artisan route:list --path=api
wslc exec erp_backend php artisan migrate:status
wslc exec erp_backend php artisan migrate            # aplica migraciones nuevas (requiere autorización)
wslc exec erp_backend php artisan optimize:clear
wslc exec erp_backend php artisan package:discover   # necesario tras optimize:clear (ver problemas conocidos)
wslc exec erp_postgres psql -U postgres -d erp_dpsit
```

## Linux con Docker Compose

```bash
./setup.sh           # o: make setup — conserva los datos (migrate + seed solo si está vacía)
./setup.sh --reset   # ⚠ recrea la base con datos demo (pide escribir BORRAR)
make up | down | restart | logs | sync-env | migrate | seed | fresh | shell-backend | shell-frontend | shell-db
```

> `docker/nginx/default.conf` usa `fastcgi_pass erp_backend:9000` (nombre del contenedor en WSLC). En
> Compose el servicio `backend` declara el alias de red `erp_backend` para que nginx lo resuelva (DEF-01,
> resuelto). `make test` ejecuta los tests: requiere
> crear antes la base `erp_dpsit_test` (ver «Tests»).

## Importar la planilla de expedientes

Requiere autorización del responsable: son datos reales del área. Los archivos van en
`backend/storage/app/importaciones/`, que está ignorado por Git.

1. Exportar las hojas a CSV UTF-8 con `;` desde su fila de encabezados: la hoja del año (encabezado en la
   fila 9) y «ORGANISMOS 2025». Sirve Excel («CSV UTF-8») o un script; el importador también acepta
   Windows-1252 y variantes como «N°».
2. Crear los usuarios del área en **Usuarios y Permisos**, con rol en Expedientes.
3. Completar `equivalencias.csv` (`nombre;email`): un renglón por cada nombre de la planilla, incluidas
   las variantes mal escritas. La simulación genera la lista en `equivalencias_pendientes.csv`.
4. Simular y revisar el informe (`informe.csv`, `destinos.csv`, `resumen.txt` en
   `storage/app/importaciones/expedientes_<fecha>_simulacion/`):

```powershell
wslc exec erp_backend php artisan expedientes:importar storage/app/importaciones/entrada/hoja_2026.csv --organismos=storage/app/importaciones/entrada/organismos.csv --equivalencias=storage/app/importaciones/entrada/equivalencias.csv --hoja=2026 --dry-run
```

5. Repetir sin `--dry-run`. El comando es idempotente: no duplica una intervención con el mismo número y
   la misma fecha de ingreso.

## Problemas conocidos del entorno (recuperados de sesiones anteriores)

| Síntoma | Causa | Solución aplicada o recomendada |
| :--- | :--- | :--- |
| `chmod(): Operation not permitted`, `bootstrap/cache must be present and writable` | Montajes NTFS en WSLC | PHP-FPM corre como root (`php-fpm -R`); ejecutar `package:discover` tras limpiar cachés |
| `Failed to fetch` desde la SPA tras `optimize:clear` | Se borra `bootstrap/cache/packages.php` y el preflight CORS falla | `php artisan package:discover` (ya incluido en `sync-env.*`) |
| `could not translate host name "postgres"` | El contenedor `erp_postgres` no tenía alias de red | Alias `--network-alias postgres` en `wslc-setup.ps1`; el responsable confirmó que lo resuelve (D-18, pendiente de commit) |
| Comillas visibles en la denominación | `.env` entre comillas, pasadas literalmente a Node | Sanitizador en `nuxt.config.ts` y `useAgency.ts` |
| La ejecución de scripts está deshabilitada | Política `Restricted` | `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass` |
| `Class "App\Http\Controllers\Controller" not found` | Autoload desactualizado | `composer dump-autoload` en `erp_backend` |
| `git add` falla con `node_modules/.bin/...` | Symlinks Linux en NTFS | `node_modules/` en `.gitignore` (ya resuelto) |

## Validación

### Tests

Desde el 2026-10-23 existen tests de feature con PHPUnit para **firma digital (22), expedientes, permisos
por módulo, usuarios e importación (26)**: 48 en total. El resto del sistema y el frontend no tienen tests.

```powershell
# Una sola vez: crear la base de pruebas (no toca erp_dpsit)
wslc exec erp_postgres psql -U postgres -c "CREATE DATABASE erp_dpsit_test"
# Ejecutar
wslc exec erp_backend php artisan test
```

- `phpunit.xml` fuerza `DB_DATABASE=erp_dpsit_test` en `<env>` **y** `<server>`. Hace falta en los dos
  porque los contenedores reciben la base de desarrollo como variable real (`$_SERVER`), que Laravel lee
  primero.
- `tests/TestCase.php` aborta si la base no termina en `_test`: es la salvaguarda contra un
  `RefreshDatabase` sobre los datos de desarrollo. Se comprobó que funciona.
- El correo usa `MAIL_MAILER=array` en tests; no llega a Mailpit.

### Otras verificaciones (solo lectura)

| Verificación | Comando (Windows) | Resultado al 2026-10-23 |
| :--- | :--- | :--- |
| Sintaxis PHP | `wslc exec erp_backend sh -c 'for f in $(find app routes config database -name "*.php"); do php -l $f >/dev/null \|\| echo FAIL $f; done'` | sin errores |
| Estilo PHP | `wslc exec erp_backend vendor/bin/pint --test` | 5 archivos con diferencias (`config/auth.php`, `config/database.php`, `config/sanctum.php`, `PersonaSeeder.php`, `routes/api.php`) |
| Rutas | `wslc exec erp_backend php artisan route:list --path=api` | 86 rutas |
| Tests | `wslc exec erp_backend php artisan test` | 48/48 |
| Tarea programada | `wslc exec erp_scheduler php artisan schedule:list` | `firma-digital:enviar-recordatorios` diaria 08:00 |
| Recordatorios (simulación) | `wslc exec erp_backend php artisan firma-digital:enviar-recordatorios --dry-run` | lista qué enviaría sin modificar nada |
| Correo recibido | `curl -s http://localhost:8025/api/v1/messages` o abrir http://localhost:8025 | mensajes capturados por Mailpit |
| Migraciones | `wslc exec erp_backend php artisan migrate:status` | 19/19 aplicadas |
| API viva | `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/` | 200 |
| Autenticación | `curl -H "Accept: application/json" http://localhost:8000/api/dashboard/stats` | 401 sin token ✔ |
| Frontend | `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/` y `wslc logs erp_frontend` | 200, Vite/Nitro compilan |
| Dependencias PHP | `wslc exec erp_backend composer audit` | avisos en 4 paquetes (ver `docs/security.md`) |
| Dependencias JS | `wslc exec erp_frontend npm audit --omit=dev` | 25 avisos (3 críticos, 20 altos) |

Prueba funcional manual: ingresar con los usuarios demo de cada perfil (ver `UserSeeder`) y recorrer el
módulo modificado. Indicar siempre en el reporte qué perfil y qué flujo se probaron.

`nuxi typecheck` no está disponible: requiere `vue-tsc`, que no está instalado.

## Convenciones de código

Resumen operativo en `AGENTS.md`. Agrega:

- Los controladores siguen el patrón `index` (filtros con `$request->filled()`), `show` (eager loading),
  `store/update` (validación inline, `AuditService::log`) y `destroy` (verifica dependencias, 422).
  Copiá ese patrón.
- Filtros booleanos: usá `filled()` y `filter_var(..., FILTER_VALIDATE_BOOLEAN)`. Con `has()`, una cadena
  vacía filtraba como `false` (bug corregido el 2026-08-19).
- Búsqueda de texto: `ILIKE` (exclusivo de PostgreSQL).
- Páginas Vue: `<script setup>` sin `lang="ts"` en la mayoría; Tailwind con las paletas `brand`/`gov`
  (`tailwind.config.js`); fuente *Plus Jakarta Sans*.
- Finales de línea: definidos en `.gitattributes` (LF en todo salvo `*.ps1`/`*.bat`, que usan CRLF).
