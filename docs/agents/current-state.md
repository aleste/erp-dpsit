# Estado actual del proyecto

Última actualización: 2026-10-23 (auditoría de transferibilidad). Actualizá esta página al cerrar cada
tarea.

## Resumen

- Funcionalidad principal implementada (ver el inventario en `docs/product.md`).
- **Módulos de firma digital y de seguimiento de expedientes, permisos por módulo y gestión de usuarios
  implementados el 2026-10-23** en la rama `feat/firma-digital`, sin commit. 48 tests de feature; el resto
  del sistema no tiene pruebas de comportamiento.
- Entorno Windows/WSLC operativo con 6 contenedores (se sumaron `erp_mailpit` y `erp_scheduler`).
  `wslc-setup.ps1` ya no borra la base. Entorno Linux sin verificar.
- Rama de trabajo `feat/firma-digital`, creada desde `develop`. `main` existe y no hay despliegues.
- Los alias de red de `wslc-setup.ps1` (`--network-alias postgres` y `--network-alias backend`) los
  confirmó el responsable el 2026-10-23 como solución del error de DNS (D-18). El cambio sigue **sin
  commit** en el working tree.
- Riesgo más relevante: SEC-01, descargas y exportación de datos personales sin autenticación
  (`docs/security.md`). Por decisión del responsable **no se corrige por ahora** (D-17): no tocar
  backend ni frontend por este motivo.

## Defectos conocidos

Todos fueron detectados por lectura de código, salvo indicación. Ninguno se corrigió en esta auditoría.

| ID | Prioridad | Defecto | Ubicación | Evidencia |
| :--- | :--- | :--- | :--- | :--- |
| DEF-01 | Alta | nginx apunta a `erp_backend:9000`, que no existe en Docker Compose (servicio `backend`, contenedor `erp_dpsit_backend`): la API probablemente no funcione en Linux | `docker/nginx/default.conf` | *Inferencia*. No hay Docker en el host. El alias `backend` sin confirmar en `wslc-setup.ps1` sugiere un cambio en curso hacia `backend:9000` |
| ~~DEF-02~~ | **Resuelto 2026-10-23** (D-23) | ~~`wslc-setup.ps1`, `wslc-setup.sh` y `setup.sh` ejecutan `migrate:fresh --seed` en cada ejecución~~. Ahora `migrate` + seed solo con la base vacía; `-Reset`/`--reset` con confirmación. Verificado: tras recrear el stack se conservaron personas, entregas y usuarios | scripts, `README.md` | Código. Hay un PDF huérfano en `backend/storage/app/documents/area_ti/` con 0 registros en `documentos_adjuntos` |
| DEF-03 | Media | `wslc-setup.sh` divergió de `wslc-setup.ps1` (sin alias de red, `DB_HOST=erp_postgres`, variables inline, **sin Mailpit ni scheduler**). Solo se le quitó el `migrate:fresh` | `wslc-setup.sh` | Código. ¿Se sigue usando? |
| DEF-04 | Media | La definición de «vigente» no es consistente: `scopeVigente()` exige `fecha_inicio <= hoy`, pero las relaciones `asignacionesVigentes` y `maximaAutoridadVigente` (en `AreaTi` y `Persona`) no lo hacen. Una designación futura aparece como vigente en listados y contadores y bloquea el borrado de personas | `app/Models/AreaTi.php`, `app/Models/Persona.php` | Código |
| DEF-05 | Baja | Precedencia en `nuxt.config.ts`: `NUXT_PUBLIC_API_BASE \|\| BACKEND_PORT ? ... : undefined` nunca usa el valor de `NUXT_PUBLIC_API_BASE` y puede producir `http://localhost:undefined/api`. Hoy no se manifiesta porque Nuxt sobrescribe `public.apiBase` en tiempo de ejecución con `NUXT_PUBLIC_API_BASE` | `frontend/nuxt.config.ts` | Código e *inferencia* sobre el comportamiento de Nuxt |
| DEF-06 | Baja | Los valores por defecto de la denominación difieren: el backend usa «…Tecnología», `AGENCY_ACRONYM=DP` y «Poder Ejecutivo Provincial»; el frontend, «…Tecnologías», `DPSIT` y «Subsecretaría de Gobierno Digital» | `config/app.php`, `docker-compose.yml`, `nuxt.config.ts`, `useAgency.ts` | Código |
| DEF-07 | Media | **Parcial**: desde el 2026-10-23 existen `phpunit.xml` y 22 tests, solo del módulo de firma digital. El resto (ministerios, áreas, personas, asignaciones, servicios, exportador, auth) sigue sin tests | `backend/tests/` | **Comprobado** |
| DEF-08 | Baja | Pint marca diferencias de estilo en 5 archivos | ver `docs/development.md` | **Comprobado** |
| DEF-09 | Media | `designarAutoridad` solo cierra las autoridades previas con `fecha_fin` nula; si la previa tiene una `fecha_fin` futura, quedan dos máximas autoridades vigentes | `AreaTiController@designarAutoridad` | Código |
| DEF-10 | Media | Soft delete combinado con `unique` en `personas.cuil_cuit` y `email_principal` (base y validación): una persona dada de baja impide volver a registrar el mismo CUIL o email | migración `000003`, `PersonaController` | Código |
| DEF-11 | Baja | `HasFactory` en todos los modelos, sin `database/factories/` | `app/Models/` | Código |
| DEF-12 | Baja | `docker-compose.yml` declara `version: '3.8'` (obsoleto en Compose v2; solo produce una advertencia) | `docker-compose.yml` | Código |
| DEF-13 | Baja | `setup.sh` ejecuta `key:generate` sobre `backend/.env`, pero Compose inyecta `APP_KEY` como variable de entorno, que tiene precedencia: la clave generada no se usaría | `setup.sh`, `docker-compose.yml` | *Inferencia* |
| DEF-15 | Media | El CSV del exportador puede no coincidir con la vista previa: `exportador/index.vue` agrega `solo_vigentes` y `solo_homologadas` a la URL solo cuando valen `true`, y `ExportService` los toma como `true` cuando faltan, así que al desmarcarlos el CSV sigue filtrando. Además, `downloadCsv` no admite `incluir_alternativos` y ningún método de `ExportController` valida la entrada | `pages/exportador/index.vue`, `ExportController`, `ExportService` | Código (detectado en la prueba de transferencia) |
| DEF-16 | Baja | `ExportService` importa `ServicioReferente` sin usarlo. El exportador solo incluye personas con asignación de rol, no a los referentes de servicios; el requisito original habla de «autoridades y referentes específicos». ¿Quedó incompleto? | `ExportService.php` | Código |
| DEF-17 | Baja | `wslc-setup.ps1` pasa las variables con `--env-file`, que quedan fijas al crear el contenedor; `sync-env.ps1` no recrea `erp_backend` ni `erp_scheduler`, así que un cambio de `.env` (p. ej. `FIRMA_RECORDATORIO_DIAS`) no se aplica hasta volver a ejecutar `wslc-setup.ps1` | `sync-env.ps1` | *Inferencia* sobre el comportamiento de `--env-file`; documentado en `docs/development.md` |
| DEF-18 | Baja | Si el proceso del comando muere entre el reclamo (ENVIANDO) y el resultado, el recordatorio queda en ENVIANDO y no se reintenta solo. Se recupera con «Reprogramar» desde la UI | `EnviarRecordatoriosVencimiento` | Código |
| DEF-14 | Info | El README atribuye al Administrador «gestión de usuarios y auditoría», funciones que no existen | `README.md` | Código |

## Pendientes funcionales identificados

Ver los estados PLAN y PARCIAL en `docs/product.md`:

- gestión de usuarios;
- consulta de auditoría;
- cambio y recuperación de contraseña;
- pantalla de ABM de tipos de rol;
- adjuntos en persona, servicio y asignación desde la UI;
- borrado de adjuntos;
- entorno de producción.

**Ninguno fue priorizado por el responsable**: no implementarlos sin pedido explícito.

## Preguntas pendientes para el responsable

1. ¿Se aprueba la estructura documental y de agentes (D-16)? ¿Se versiona?
2. ~~¿Se corrige SEC-01 ahora?~~ **Respondida (2026-10-23):** no; no se tocan el backend ni el frontend
   (D-17).
3. ¿Se adopta como regla general que los cambios de esquema sean migraciones nuevas (D-08)? En la práctica,
   ya se aplicó así en el módulo de firma digital.
4. ~~¿`wslc-setup.ps1` debe dejar de ejecutar `migrate:fresh`?~~ **Respondida (2026-10-23):** sí;
   implementado (D-23).
5. ~~¿Se confirma el cambio pendiente de `wslc-setup.ps1`?~~ **Respondida (2026-10-23):** sí, resolvió el
   problema (D-18). Sigue abierto: ¿se alinea nginx con `backend:9000` para que funcione igual en WSLC
   y en Compose (DEF-01)? Con el alias `backend` ya presente en WSLC, ese cambio no rompería Windows
   *(inferencia, no probado)*.
6. ¿`wslc-setup.sh` sigue en uso o se retira?
7. ~~¿Los datos de `PersonaSeeder` son ficticios?~~ **Respondida (2026-10-23):** sí, son ficticios.
8. Tras D-05, ¿dónde se registran los datos técnicos de los servicios (ID de proyecto de nube, recursos)?
9. ¿Qué se prioriza después: tests, seguridad o funcionalidades pendientes?
10. ¿Qué agente se usará principalmente (Claude Code, Codex u otro) y si habrá trabajo en paralelo?
11. ¿El exportador debe incluir a los referentes de servicios (DEF-16)? En ese caso: ¿cuentan solo los
    servicios ACTIVO? ¿Los filtros se combinan por unión o por intersección? ¿Se deduplica por persona?
13. Expedientes: preguntas P5–P13 de `docs/proposals/modulo-expedientes.md` (plazos y avisos, circuito de
    estados, ETB, acto administrativo, firma, revisor, números 5900-, integración con GDE).
12. Firma digital: texto definitivo del correo y remitente; datos SMTP de producción; ¿copia a una casilla
    institucional?; ¿se adjunta el acta de entrega?; ¿hace falta una pantalla propia de stock de
    dispositivos?

## Tareas en curso y bloqueos

| Tarea | Agente / persona | Rama | Estado | Bloqueo |
| :--- | :--- | :--- | :--- | :--- |
| Importación real de la planilla de expedientes (hoja 2026) | Responsable + agente | `feat/firma-digital` | simulación hecha | crear usuarios del área, completar equivalencias, autorizar |

## Registro de tareas

Agregar una fila por tarea cerrada, la más reciente arriba.

| Fecha | Agente | Tarea | Archivos | Validación ejecutada | Pendientes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 2026-10-24 | Claude Code (Opus 5.5) | Copia de los recordatorios de firma digital al buzón del área (`FIRMA_RECORDATORIO_CC`) | rama `feat/firma-recordatorio-cc`: `config/firma_digital.php`, `EnviarRecordatoriosVencimiento`, `EnviarRecordatoriosTest` (2 tests nuevos), `phpunit.xml`, `.env.example`, `docker-compose.yml`; `docs/decisions.md` (D-25), `product.md`, `development.md` | 50/50 tests; Pint en los archivos tocados; lectura de la variable con varias direcciones y una inválida | definir el buzón real en `.env` y recrear backend y scheduler; sin envío real contra Mailpit |
| 2026-10-23 | Claude Code (Opus 5.5) | Módulo de seguimiento de expedientes, permisos por módulo y gestión de usuarios | 3 migraciones, 7 modelos, middleware `modulo`, `ExpedienteService`, 4 controladores, comando de importación, seeder de catálogos, `config/expedientes.php`; `User`, `AuthController`, rutas; páginas `expedientes/*` y `usuarios`, componentes, composables `useExpedientes` y `useFechas`, store de auth, menú, `tailwind.config.js` (agrega `composables/`); docs | 48/48 tests; `migrate` (19/19) y catálogos en la base de desarrollo; simulación de importación con la hoja 2026 real; tablero y ficha vistos en Chrome (expediente ficticio dado de baja después) | importación real; recorrer acciones de la ficha, usuarios y catálogos en la UI; P5–P13 |
| 2026-10-23 | Claude Code (Opus 5.5) | Módulo de firma digital (tokens) y scripts de arranque sin `migrate:fresh` | rama `feat/firma-digital` (sin commit): 4 migraciones, 3 modelos, 3 controladores, servicio, comando, Mailable y vista, config, seeder, `phpunit.xml` y tests; `Persona`, `PersonaController`, `routes/*`; página `firma-digital`, 3 componentes, composable, `personas/index.vue`, menú, `useToast`; `wslc-setup.ps1`, `wslc-down.ps1`, `setup.sh`, `wslc-setup.sh`, `docker-compose.yml`, `.env.example` (y `.env` local); docs | 22/22 tests; `migrate` en la base de desarrollo (16/16); lint y Pint de los archivos nuevos; recreación del stack conservando datos; comando real contra Mailpit e idempotencia; UI recorrida en Chrome (perfil EDITOR) | preguntas 3, 12; commit/PR; Docker Compose sin probar |
| 2026-10-23 | Claude Code (Opus 5.5) | Registro de respuestas del responsable (SEC-01 pospuesto, alias WSLC confirmado, seed ficticio) | `AGENTS.md`, `docs/decisions.md` (D-17, D-18), `docs/security.md`, `docs/product.md`, `docs/development.md`, `docs/architecture.md`, este archivo | enlaces y búsqueda de secretos | preguntas 1, 3, 4, 6, 8–11 |
| 2026-10-23 | Claude Code (Opus 5.5) | Auditoría de transferibilidad y preparación multiagente | Ver `docs/agents/audit-report-2026-10-23.md` §C | lint PHP, pint --test, route:list, migrate:status, sondeos HTTP, audits de dependencias, conteos en la base, verificación de enlaces | Preguntas pendientes 1–10 |
| 2026-10-23 | Antigravity IDE (Gemini) | Alias de red en `wslc-setup.ps1` | `wslc-setup.ps1` (confirmado por el responsable; sin commit) | según su transcripción: recreó `erp_postgres` y ejecutó `migrate:fresh --seed` | commit |
| 2026-09-14 | (responsable / Antigravity) | `PersonaSearchSelect` | commit `b26c67e` | desconocida | — |
| 2026-08-19/24 | Antigravity CLI (Gemini) | Desarrollo inicial completo, toasts, menú colapsable, scripts Linux | commits `65b5a7c`–`688478b` | manual, según la transcripción | — |
