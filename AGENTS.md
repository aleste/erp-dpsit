# AGENTS.md — ERP DPSIT

Instrucciones compartidas para cualquier agente de IA (Codex, Claude Code u otros) y para personas que
colaboren en el repositorio. Es la **fuente única** de reglas de trabajo; el detalle técnico vive en `docs/`.
Mantener este archivo por debajo de ~150 líneas.

## Qué es

ERP de gobernanza TI para una Dirección Provincial rectora de tecnología: ministerios, áreas TI
homologadas, padrón de personas con roles y vigencias, catálogo de servicios tecnológicos asignados a
áreas (con referentes y documentación adjunta), exportador de contactos, entrega de tokens de firma
digital con recordatorios de vencimiento por email y seguimiento de expedientes administrativos de un
área interna. Perfiles: ADMINISTRADOR, EDITOR, LECTOR. Etapa:
desarrollo local, **sin producción**; hay tests automatizados **solo** para firma digital, expedientes y
usuarios.

## Mapa del repositorio

| Ruta | Contenido |
| :--- | :--- |
| `backend/` | API REST Laravel 11 (PHP 8.3), Sanctum, Eloquent, PostgreSQL 16 |
| `backend/routes/api.php` | Todas las rutas y su control de perfiles |
| `backend/app/Http/Controllers/Api/` | Un controlador por recurso; validación inline con `$request->validate` |
| `backend/app/Services/` | `AuditService` (bitácora), `ExportService` (exportación) |
| `backend/database/migrations/`, `seeders/` | Esquema y datos demo |
| `backend/tests/Feature/` | Tests de feature (PHPUnit, base `erp_dpsit_test`) |
| `backend/app/Console/Commands/`, `routes/console.php` | Comandos y tareas programadas (contenedor `erp_scheduler`) |
| `frontend/` | SPA Nuxt 3 (`ssr: false`), Vue 3 `<script setup>`, Pinia, Tailwind |
| `frontend/composables/useApi.ts` | Único cliente HTTP (token Bearer en `localStorage`) |
| `docker/`, `docker-compose.yml` | Imágenes y stack (Linux/Docker Compose) |
| `wslc-*.ps1`, `sync-env.ps1` | Stack en Windows con WSLC (entorno usado a diario) |
| `docs/` | Documentación del proyecto (índice en `docs/README.md`) |
| `.agents/skills/` | Procedimientos reutilizables (fuente común de skills) |

## Fuentes de verdad

| Tema | Archivo |
| :--- | :--- |
| Índice de documentación | `docs/README.md` |
| Arquitectura y modelo de datos | `docs/architecture.md` |
| Contrato de la API | `docs/api.md` (el código manda: `backend/routes/api.php`) |
| Módulos y requisitos | `docs/product.md` |
| Entorno, comandos y validación | `docs/development.md` |
| Seguridad | `docs/security.md` |
| Decisiones | `docs/decisions.md` |
| Estado actual, defectos y pendientes | `docs/agents/current-state.md` |
| Cómo iniciar/cerrar tareas y trabajar en paralelo | `docs/agents/workflow.md` |

Si la documentación contradice al código, **el código es la verdad**: corregí el documento y anotalo.

## Antes de modificar nada

1. Leé `docs/agents/current-state.md` y la sección de la tarea en `docs/agents/workflow.md`.
2. Ejecutá `git status` y `git branch --show-current`. No pises cambios ajenos sin confirmar.
3. Para cambios funcionales, aplicá la skill `analisis-impacto` (`.agents/skills/analisis-impacto/SKILL.md`).

## Cómo ejecutar y validar

- Entorno Windows (el habitual): contenedores `erp_postgres`, `erp_backend`, `erp_webserver`, `erp_frontend`,
  `erp_mailpit` (correo de desarrollo, buzón http://localhost:8025) y `erp_scheduler` (tareas programadas).
  Comandos dentro del backend: `wslc exec erp_backend php artisan <cmd>`.
- Entorno Linux: `docker compose exec backend php artisan <cmd>` (ver advertencias en `docs/development.md`).
- Frontend: http://localhost:3000 · API: http://localhost:8000/api.
- Tests: `wslc exec erp_backend php artisan test` (corren contra `erp_dpsit_test`, nunca contra la base de
  desarrollo). Solo cubren firma digital; el resto del sistema no tiene tests: no afirmes que algo está
  "probado" si solo compiló. Validación mínima tras un cambio: skill `validar-cambios`.

## Convenciones

- Idioma del dominio, código, mensajes y UI: **español** (tablas, modelos, campos y textos). Mantenelo.
- Backend: respuestas JSON `{ success, message?, data, stats? }`; errores de negocio con 422 y `message`.
  Toda escritura registra `AuditService::log(ACCION, MODULO, id, anterior, nuevo)`.
- Bajas: modelos con `SoftDeletes`; el borrado está reservado a ADMINISTRADOR y verifica dependencias.
- Permisos: se aplican en `routes/api.php`: el middleware `perfil:` controla el perfil global y `modulo:`
  (p. ej. `modulo:EXPEDIENTES,OPERADOR`) controla el rol por módulo (D-26). Un módulo de un área interna
  usa `modulo:`, no `perfil:`. En el frontend, los `v-if` con `authStore.isEditor/isAdmin/puedeModulo`
  son solo cosméticos.
- Correo: siempre vía `Mail` de Laravel; en desarrollo `MAIL_HOST=mailpit` (nunca un SMTP real).
- Frontend: llamadas HTTP solo vía `useApi()`; notificaciones solo con `useToast()` (`toast.*`,
  `confirmDialog`). **Prohibido** `alert()`/`confirm()` nativos. Para elegir personas usar
  `PersonaSearchSelect` (búsqueda remota; el padrón puede superar 2.000 personas). Todo modal lleva
  `v-dialogo="() => cerrar()"` en su panel (`plugins/dialogo.ts`: rol, foco, Esc) y cada `<label>`
  se vincula a su campo con `for`/`id` (en `PersonaSearchSelect`, prop `input-id`). Tipografía: cuerpo,
  tablas y controles en `text-sm`; metadatos y badges en `text-xs` (piso de 12 px: no usar `text-[Npx]`);
  mayúsculas espaciadas solo en encabezados de tabla, badges e indicadores; texto gris sobre fondo claro
  desde `slate-500` (`slate-600` sobre `slate-100`).
- Denominación de la institución: nunca hardcodear; usar `config('app.agency.*')` / `useAgency()`.
- Estilo PHP: Laravel Pint (`vendor/bin/pint`), aplicado solo a los archivos que tocaste.
- Git: `develop` = desarrollo, `main` = producción. Trabajar en ramas desde `develop`. Commits estilo
  Conventional Commits en español (`feat:`, `fix:`, `docs:`…).

## Acciones que requieren autorización explícita del responsable

- `migrate:fresh`, `db:wipe`, borrar volúmenes, `make fresh`, o `wslc-setup.ps1 -Reset` / `setup.sh --reset`
  (**recrean la base de datos**). Sin esas opciones, los scripts de arranque conservan los datos.
- Modificar migraciones ya existentes (hoy se editaron in situ; ver `docs/decisions.md` D-08).
- Actualizar dependencias, cambiar imágenes Docker, instalar paquetes globales o servidores MCP.
- `git push`, merges a `main`, reescritura de historial, commits en nombre del usuario.
- Cualquier acción contra servicios externos o datos reales.

## Seguridad obligatoria

- No copiar valores de `.env`, contraseñas, `APP_KEY` ni tokens en documentos, commits ni prompts.
- Todo endpoint nuevo va dentro de `auth:sanctum` y con el `perfil:` correcto. Hay dos rutas públicas
  heredadas que son un defecto conocido (ver `docs/security.md` SEC-01); no agregues más. Por decisión
  del responsable (`docs/decisions.md` D-17) **no se corrigen por ahora**: no modifiques backend ni
  frontend para resolverlas sin una nueva autorización.
- Validar toda entrada en el controlador; no concatenar SQL con datos del usuario.
- Datos personales (CUIL, emails, teléfonos, nombres en carátulas de expedientes): no volcarlos en logs
  ni en ejemplos de documentación. Los archivos de importación con datos reales van en
  `backend/storage/app/importaciones/` (ignorado por Git) y su importación requiere autorización.

## Al terminar una tarea

1. Ejecutá la validación que corresponda y registrá qué corriste y qué no.
2. Actualizá la documentación afectada (skill `actualizar-documentacion` en `.agents/skills/`).
3. Registrá en `docs/agents/current-state.md` (sección «Registro de tareas») archivos tocados, validaciones
   y pendientes.
4. Reportá hechos verificados por separado de las suposiciones.

## Si hay contradicciones

Prioridad: indicación explícita del responsable en la sesión > este `AGENTS.md` > `docs/` > comentarios
del código. Ante una contradicción no resuelta, preguntá antes de actuar y registrala en
`docs/agents/current-state.md` («Preguntas pendientes»).
