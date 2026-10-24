# Producto: requisitos y módulos

## Requisitos originales (recuperados)

Fuente: pedido inicial del responsable al agente anterior (Google Antigravity, 2026-08-19). Se transcribió
de las conversaciones locales de esa herramienta; **no** estaba en el repositorio.

1. Gestionar **ministerios** y sus **áreas TI homologadas** (un ministerio, muchas áreas).
2. **Padrón de personas**: apellido, nombre, teléfonos principal/alternativo, emails principal/alternativo,
   datos básicos.
3. **Asignación de roles** en un área TI (director provincial, director, referente de seguridad, técnico,
   administrativo, etc.) con **vigencias temporales** (fecha inicio/fin). Una persona puede tener cargos
   distintos en distintas áreas o ministerios. Identificar la **máxima autoridad** de cada área TI
   homologada, independientemente de la denominación del cargo.
4. **Servicios tecnológicos parametrizables** que la Dirección provee a las áreas (nube, SSO,
   interoperabilidad, …), con referentes técnicos/administrativos y **documentación adjunta** (actas,
   anexos técnicos).
5. **Búsquedas avanzadas y filtros cruzados**: por persona (todas sus asignaciones) y por rol (todas las
   personas con ese rol).
6. **Exportación** de contactos/correos filtrados (p. ej. máximas autoridades o referentes de seguridad)
   para campañas de notificación.
7. **Perfiles**: Administrador (control total, usuarios y tablas de referencia), Editor (ABM de áreas,
   servicios, personas, roles y documentos), Lector (solo lectura y consultas).
8. Stack: PostgreSQL, Laravel (API REST, migraciones, seeds, Eloquent), Nuxt/Vue 3 `<script setup>`,
   entorno dockerizado sobre **WSLC** en Windows; luego también Linux (Ubuntu).

Requisitos no funcionales mencionados después:

- denominación institucional configurable por entorno (puede cambiar con el gobierno);
- notificaciones visuales homogéneas en lugar de `alert()`;
- selector de personas apto para más de 2.000 registros;
- producción futura en una VM VMware con Ubuntu Server (solo se pidió una estimación de requisitos; no hay
  nada implementado).

Requisito agregado el 2026-10-23: **seguimiento de expedientes** de un área interna, que reemplaza su
planilla Excel (relevamiento y diseño en `docs/proposals/modulo-expedientes.md`; D-26 a D-31).

Requisito agregado el 2026-10-23: **módulo de dispositivos de firma digital**. Incluye personas con
organismo opcional (también externas al ERP), entregas de tokens con historial, vigencia del certificado
(un año por defecto, editable), recordatorios por email configurables (30 días por defecto) enviados
automáticamente al titular, con copia opcional al buzón del área (`FIRMA_RECORDATORIO_CC`), sin duplicados y tolerando personas sin email. Diseño en
`docs/proposals/modulo-firma-digital.md`; decisiones D-19 a D-22.

## Inventario de módulos

Estados:

- **IV**: implementado y verificado;
- **INV**: implementado, no verificado;
- **PARCIAL**: parcialmente implementado;
- **PLAN**: planificado, sin evidencia de implementación;
- **?**: estado indeterminado.

«Verificado» exige una prueba de comportamiento. Al 2026-10-23 **no hay tests** y esta auditoría solo
comprobó lo siguiente:

- las rutas se registran;
- el lint de PHP no da errores;
- el frontend compila en modo dev y responde 200;
- la API rechaza sin token (401) y la ruta de descarga no exige autenticación.

Por eso ningún módulo alcanza «IV».

| Módulo | Ubicación principal | Estado | Evidencia | Riesgos / dudas |
| :--- | :--- | :--- | :--- | :--- |
| Login / sesión | `AuthController`, `pages/login.vue`, `stores/auth.ts` | INV | 401 sin token comprobado; flujo de login no ejecutado | tokens sin expiración, sin límite de intentos (SEC-04) |
| Panel de control | `DashboardController`, `pages/index.vue` | INV | código | — |
| Ministerios (ABM) | `MinisterioController`, `pages/ministerios/index.vue` | INV | código; el usuario reportó un bug de refresco el 2026-08-19, luego corregido según el agente anterior | — |
| Áreas TI (ABM, expediente, máxima autoridad) | `AreaTiController`, `pages/areas-ti/index.vue`, `pages/areas-ti/[id].vue` | INV | código | DEF-04 (vigencias futuras), DEF-09 (doble autoridad) |
| Personas (padrón, historial) | `PersonaController`, `pages/personas/index.vue` | INV | código | DEF-10 (no se puede re-registrar un CUIL/email dado de baja) |
| Asignación de roles y vigencias | `AsignacionRolController`, `pages/roles/index.vue`, `pages/areas-ti/[id].vue` | INV | código | DEF-04 |
| Tipos de rol (tabla de referencia) | `TipoRolController` | PARCIAL | API completa (solo Admin); **sin pantalla de ABM**; los datos vienen del seeder | ¿se requiere UI? |
| Catálogo de servicios | `CatalogoServicioController`, `pages/servicios/index.vue` (pestaña catálogo) | INV | código | la parametrización JSON se eliminó a pedido (D-05) |
| Servicios por área y referentes | `ServicioAreaController`, `pages/servicios/index.vue`, `pages/areas-ti/[id].vue` | INV | código | — |
| Documentos adjuntos | `DocumentoAdjuntoController`, `pages/areas-ti/[id].vue` | PARCIAL | subida/descarga solo desde el expediente de **área TI**; la API admite persona, servicio y asignación sin UI; borrado sin UI | descarga pública (SEC-01) |
| Exportador de contactos | `ExportController`, `ExportService`, `pages/exportador/index.vue` | PARCIAL | código: exporta solo personas con asignación de rol; **no** incluye referentes de servicios (requisito 6) | descarga CSV pública (SEC-01); CSV distinto de la vista previa (DEF-15, DEF-16) |
| Auditoría | `AuditService`, tabla `audit_logs` | PARCIAL | se registra; **no hay consulta ni UI** | — |
| Gestión de usuarios y permisos por módulo | `UsuarioController`, `pages/usuarios/index.vue`, `CheckModuloAcceso` | **IV** (API) / INV (UI) | 4 tests de API + 6 de permisos; pantalla no recorrida en el navegador | sin recuperación de contraseña |
| **Seguimiento de expedientes** | `ExpedienteController`, `ExpedienteIntervencionController`, `ExpedienteService`, `pages/expedientes/*`, `components/expedientes/` | **IV** (API) / INV (UI) | 12 tests del circuito (ingreso, reingreso, estados, asignación, egreso, reapertura, corrección, filtros, tablero, exportación, baja); tablero y ficha vistos en el navegador; acciones de la ficha no recorridas en la UI | sin avisos por correo (D-31); preguntas P5–P13 de la propuesta |
| Importación de la planilla de expedientes | `ImportarExpedientesPlanilla` | **IV** (con datos de prueba) | 4 tests; simulación con la hoja 2026 real: 1.079 intervenciones, 97 abiertas, 221 → 173 destinos | **importación real pendiente**: requiere usuarios del área y archivo de equivalencias |
| Recuperación/cambio de contraseña | tabla `password_reset_tokens` | PLAN | sin rutas | — |
| Notificaciones toast y confirmaciones | `useToast`, `stores/notification.ts`, `ToastContainer`, `ConfirmModal` | INV | código; `grep` no encuentra `alert(`/`confirm(` nativos | — |
| Menú lateral colapsable | `layouts/default.vue` | INV | commit `688478b` | — |
| Selector de personas con búsqueda | `components/PersonaSearchSelect.vue` | INV | commit `b26c67e` (debounce, mín. 2 caracteres, 15 resultados) | — |
| Denominación institucional configurable | `config/app.php`, `nuxt.config.ts`, `useAgency.ts` | INV | código | valores por defecto distintos entre capas (DEF-06) |
| Entorno Linux / Docker Compose | `docker-compose.yml`, `setup.sh`, `Makefile` | ? | nunca ejecutado en esta auditoría (sin Docker en el host) | DEF-01 (host FastCGI) |
| **Firma digital: personas, tokens, certificados** | `EntregaTokenController`, `CertificadoFirmaController`, `DispositivoFirmaController`, `FirmaDigitalService`, `pages/firma-digital/index.vue`, `components/firma-digital/` | **IV** (API) / INV (UI) | 14 tests de feature de API y cálculos (PHPUnit, 2026-10-23); UI recorrida en navegador: listado, filtros, detalle y vista previa de fechas. **No** se envió un alta desde la UI | sin pantalla propia de stock de dispositivos (solo API y selector en el alta); el acta de entrega no se adjunta |
| **Firma digital: recordatorios automáticos** | `app/Console/Commands/EnviarRecordatoriosVencimiento.php`, `routes/console.php`, contenedores `erp_scheduler` y `erp_mailpit` | **IV** | 8 tests (envío único, sin email, email alternativo, vencido, reintentos, dry-run, programación); ejecución real contra Mailpit el 2026-10-23 | SMTP de producción sin definir; un certificado puede quedar en ENVIANDO si el proceso muere a mitad de un envío (DEF-18) |
| Producción (VMware/Ubuntu) | — | PLAN | solo estimación de requisitos en la conversación anterior | sin compose de producción, HTTPS, backups ni build de producción |

## Datos de demostración

`DatabaseSeeder` carga:

- 25 organismos reales con su máxima autoridad vigente (`MinisterioSeeder`, D-32; idempotente, se puede
  ejecutar sobre una base con datos con `db:seed --class=MinisterioSeeder`), 6 áreas TI demo, 8 tipos de
  rol y 8 personas demo;
- 8 asignaciones, 4 servicios de catálogo y 3 instancias con referentes;
- 3 usuarios demo (uno por perfil);
- `FirmaDigitalSeeder`: 1 persona externa sin email, 5 dispositivos y 5 entregas en todos los estados
  (vigente, próximo a vencer, vencido, reemplazada). Las fechas son relativas al día de ejecución y el
  seeder no duplica datos si ya existen entregas.

Conteo verificado en la base local el 2026-10-23.

Los datos de personas son **ficticios** (confirmado por el responsable el 2026-10-23), aunque usan
CUIL con formato válido y direcciones `@gmail.com`.
