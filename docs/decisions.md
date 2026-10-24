# Registro de decisiones

Formato breve tipo ADR. Origen posible:

- **Responsable**: pedido o confirmación explícita del responsable, recuperada de las conversaciones con
  el agente anterior (Antigravity, agosto–octubre de 2026);
- **Código**: deducida de la implementación, sin registro de quién la tomó;
- **Pendiente**: requiere confirmación.

Para agregar una decisión, copiá la plantilla del final y usá el siguiente número libre.

| ID | Fecha | Decisión | Origen | Estado |
| :--- | :--- | :--- | :--- | :--- |
| D-01 | 2026-08-19 | Stack: PostgreSQL + Laravel (API REST, Eloquent) + Nuxt 3 / Vue 3 `<script setup>`, todo en contenedores | Responsable | Vigente |
| D-02 | 2026-08-19 | Entorno Windows con **WSLC** (no Docker Desktop); scripts `wslc-*.ps1` | Responsable | Vigente |
| D-03 | 2026-08-21 | Soporte adicional para Linux (Ubuntu) con Docker Compose; el desarrollo alterna entre dos computadoras vía GitHub | Responsable | Vigente; la paridad entre ambos entornos no está verificada (DEF-01) |
| D-04 | 2026-08-21 | Ramas `develop` (desarrollo) y `main` (producción) | Responsable | Vigente. El README todavía muestra `git push origin main` como flujo diario (contradicción) |
| D-05 | 2026-08-19 | Se **elimina** la parametrización JSON de servicios (`parametros_configuracion` en `servicios_areas`, `schema_configuracion` en `catalogo_servicios`) de la base, la API y la UI | Responsable | Vigente. Contradice en parte el requisito original de «servicios parametrizables (ID de proyecto, recursos)»; confirmar si esos datos se registrarán en `observaciones` o en otro lugar |
| D-06 | 2026-08-19 | Denominación institucional configurable por variables `AGENCY_*` (puede cambiar con el gobierno) | Responsable | Vigente |
| D-07 | 2026-08-19 | UI de notificaciones unificada: toasts y modal de confirmación propios; prohibidos `alert`/`confirm` nativos; traducción de los errores de Laravel en el cliente y `lang/es` en el backend | Responsable | Vigente |
| D-08 | 2026-08-19 | Durante el desarrollo inicial, los cambios de esquema se hicieron **editando las migraciones originales** y recreando la base con `migrate:fresh --seed` | Código y transcripción | Práctica abandonada desde el 2026-10-23: el módulo de firma digital usó migraciones nuevas, aplicadas con `migrate` con autorización del responsable. Falta confirmarlo como regla general |
| D-09 | 2026-08-24 | Menú lateral colapsable (mini-sidebar con íconos y tooltips, estado en `localStorage`) | Responsable (eligió la opción A) | Vigente |
| D-10 | 2026-08-24 | Selección de personas con autocompletado remoto (debounce, mínimo 2 caracteres) por un padrón estimado de más de 2.000 personas | Responsable (eligió la opción 1) | Vigente |
| D-11 | — | Autenticación con tokens personales de Sanctum (Bearer) guardados en `localStorage`; autorización por perfil con middleware `perfil:` en las rutas | Código | Vigente; ver SEC-04 |
| D-12 | — | Bajas con soft delete y bitácora de auditoría propia (`audit_logs`) en lugar de un paquete externo | Código | Vigente |
| D-13 | — | Descargas mediante rutas públicas, por simplicidad del frontend (`<a href>`) | Código | Vigente por ahora; corrección pospuesta (D-17) |
| D-14 | — | SPA sin SSR (`ssr: false`), servida con `nuxi dev` incluso dentro del contenedor | Código | Vigente en desarrollo; producción indefinida |
| D-15 | — | PHP-FPM como root para tolerar los montajes NTFS de WSLC | Código | Solo desarrollo |
| D-16 | 2026-10-23 | Documentación e instrucciones de agentes independientes del proveedor: `AGENTS.md` como fuente común, `CLAUDE.md` que la importa y skills en `.agents/skills/` con adaptadores en `.claude/skills/` | Propuesta de esta auditoría | **Pendiente** de aprobación del responsable |
| D-17 | 2026-10-23 | **No** se corrige SEC-01 por ahora: no se modifican el backend ni el frontend para proteger las rutas públicas de descarga y exportación. El riesgo queda abierto y aceptado mientras el sistema sea solo de desarrollo local | Responsable | Vigente. Revisar antes de exponer el sistema fuera de la máquina de desarrollo |
| D-19 | 2026-10-23 | Módulo de firma digital: los titulares de tokens se registran en la tabla existente `personas`; `cuil_cuit`, `email_principal` y `telefono_principal` pasan a ser opcionales y se agregan `tipo_documento`/`numero_documento` | Responsable | Aprobada, sin implementar |
| D-20 | 2026-10-23 | El organismo del titular se registra en cada entrega: `ministerio_id` opcional más el texto libre `organismo_externo` | Responsable | Aprobada, sin implementar |
| D-21 | 2026-10-23 | Certificados en una tabla propia (`certificados_firma`, varios por entrega) para renovar sin cambiar de token | Responsable | Aprobada, sin implementar |
| D-22 | 2026-10-23 | Recordatorios con un comando programado y un contenedor `erp_scheduler` (`schedule:work`). En desarrollo, el correo va a un contenedor **Mailpit** (`erp_mailpit`): captura todos los mensajes y no los entrega afuera, y se pueden ver en un buzón web local. Reemplaza la opción inicial `MAIL_MAILER=log` | Responsable (ajustada el mismo día) | Aprobada, sin implementar |
| D-23 | 2026-10-23 | Los scripts de arranque (`wslc-setup.ps1`, `setup.sh`, `wslc-setup.sh`) **conservan los datos**: `migrate` y seed solo con la base vacía. Recrear la base exige `-Reset`/`--reset` y escribir BORRAR | Responsable (autorizó corregir el `migrate:fresh`) | Vigente |
| D-24 | 2026-10-23 | Tests de feature con PHPUnit contra una base PostgreSQL separada (`erp_dpsit_test`), con doble salvaguarda (variables forzadas y verificación del nombre de la base). Se usa PostgreSQL y no SQLite porque el código depende de `ILIKE`, `DISTINCT ON` e índices parciales | Implementación (propuesta aprobada en D-19 a D-22) | Vigente |
| D-25 | 2026-10-23 | Envío de recordatorios: al email principal y, si no es válido, al alternativo. Si el certificado ya venció sin enviar el recordatorio, este se cancela. Hasta 3 intentos ante errores. Se reclama cada registro con un UPDATE condicional (estado ENVIANDO) para no duplicar envíos | Supuestos anunciados al responsable antes de implementar | Vigente; revisable |
| D-26 | 2026-10-23 | Permisos **por módulo** además del perfil global: cada usuario puede tener un rol por módulo (para Expedientes: CONSULTA < OPERADOR < SUPERVISOR). El ADMINISTRADOR global accede a todo | Responsable | Aprobada |
| D-27 | 2026-10-23 | Pantalla de **administración de usuarios** (solo ADMINISTRADOR): alta, perfil, roles por módulo, activar/desactivar; los usuarios no se borran | Responsable | Aprobada |
| D-28 | 2026-10-23 | Los organismos de la planilla de expedientes se **unifican** en el catálogo `ministerios` del ERP (sin duplicar los existentes) | Responsable | Aprobada |
| D-29 | 2026-10-23 | Migración de expedientes: **solo la hoja 2026**; 2024–2025 y las hojas auxiliares quedan en la planilla como archivo de consulta | Responsable | Aprobada |
| D-30 | 2026-10-23 | Modelo expediente → intervenciones (cada pase por el área = una fila de la planilla) → movimientos de solo inserción; estados, tipos de requerimiento y destinos como catálogos configurables; tipos de movimiento fijos en el código porque los genera el sistema | Propuesta aprobada implícitamente al validar P1–P4 | Vigente; revisable |
| D-31 | 2026-10-23 | Sin dependencias nuevas: la importación lee **CSV** exportado de la planilla (no `.xlsx`); sin avisos por correo hasta confirmar plazos reales (P5); transiciones de estado libres pero registradas (P6) | Criterio conservador de la propuesta, a confirmar | Vigente; revisable |
| D-32 | 2026-10-24 | Cada ministerio u organismo tiene una **máxima autoridad** con historial (`autoridades_organismo`; cargo en texto libre). Los 25 organismos del Poder Ejecutivo y sus autoridades vigentes se versionan en `MinisterioSeeder` (datos reales y públicos, por indicación del responsable; reemplaza los 6 ministerios demo). Nombres en formato «Apellido» / «Nombres» capitalizados | Responsable | Aprobada, implementada |
| D-18 | 2026-10-23 | Alias de red WSLC `--network-alias postgres` (en `erp_postgres`) y `--network-alias backend` (en `erp_backend`) en `wslc-setup.ps1` | Responsable (confirmó que resolvió el error de DNS) | Vigente; pendiente de commit |

## Plantilla

```markdown
| D-NN | AAAA-MM-DD | <decisión en una frase> | Responsable / Código / Propuesta | Vigente / Pendiente / Reemplazada por D-MM |
```

Si una decisión requiere más contexto (alternativas y consecuencias), crear `docs/decisions/D-NN-<tema>.md`
y enlazarlo desde la tabla.
