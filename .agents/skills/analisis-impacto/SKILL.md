---
name: analisis-impacto
description: Analiza el impacto de un cambio funcional en ERP DPSIT (rutas, controladores, modelos, migraciones, páginas Nuxt, permisos y documentación) antes de modificar código. Usar antes de implementar o corregir cualquier funcionalidad.
---

# Análisis de impacto (solo lectura)

## Entradas

- Descripción del cambio y perfil(es) afectados (ADMINISTRADOR / EDITOR / LECTOR).
- Módulo: ministerios, áreas TI, personas, roles/asignaciones, servicios, documentos, exportador, auth.

## Procedimiento

1. Ubicá los endpoints en `backend/routes/api.php` y su grupo de perfil. Cruzalos con la tabla de `docs/api.md`.
2. Leé el controlador correspondiente en `backend/app/Http/Controllers/Api/` y los modelos en
   `backend/app/Models/` (relaciones, `$fillable`, `$casts`, `$appends`, *scopes*).
3. Si cambia la estructura de datos: revisá la migración en `backend/database/migrations/` y los seeders
   que cargan esa tabla. En ese caso, seguí la skill `cambio-esquema-bd`.
4. Buscá los consumidores del frontend:
   `grep -rn "<endpoint>" frontend/pages frontend/components frontend/composables`.
   Tené en cuenta que varias páginas llaman al mismo endpoint (por ejemplo, `/personas` se usa en cinco
   archivos).
5. Revisá los efectos transversales:
   - `AuditService::log` (módulo y acción);
   - `morphMap` en `AppServiceProvider` (si hay relaciones polimórficas);
   - `ExportService` (si cambian campos de persona, asignación o área);
   - traducciones en `frontend/composables/useToast.ts` y `backend/lang/es/validation.php`;
   - contadores de `DashboardController`.
6. Revisá los riesgos conocidos que aplican: `docs/agents/current-state.md` (DEF-xx) y
   `docs/security.md` (SEC-xx).
7. Verificá que no haya otro agente tocando los mismos archivos («Tareas en curso» en
   `current-state.md`) y si alguno es de alta contención (`docs/agents/workflow.md` §5).

## Resultado esperado

Un informe breve, antes de editar:

```markdown
**Cambio:** …
**Archivos a modificar:** backend … / frontend … / docs …
**Endpoints afectados:** método + ruta + perfil
**Datos:** ¿cambia el esquema? ¿se pierden datos locales?
**Riesgos:** DEF/SEC relacionados, consumidores múltiples, permisos
**Autorizaciones necesarias:** …
**Plan de validación:** (ver skill validar-cambios)
```

## Restricciones

- No modificar archivos durante el análisis.
- No ejecutar `migrate`, `migrate:fresh`, `db:seed` ni los scripts `wslc-setup.ps1`/`setup.sh`.
- Si el pedido es ambiguo o contradice `docs/decisions.md`, preguntá en lugar de suponer.
