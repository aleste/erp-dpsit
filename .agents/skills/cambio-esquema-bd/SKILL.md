---
name: cambio-esquema-bd
description: Procedimiento seguro para cambiar el esquema de PostgreSQL en ERP DPSIT (migraciones Laravel, modelos, seeders, validaciones y frontend asociados). Usar cuando un cambio agrega, modifica o elimina tablas, columnas, índices o restricciones.
---

# Cambio de esquema de base de datos

## Contexto

- Las 12 migraciones (`backend/database/migrations/2026_01_01_0000NN_*`) se editaron directamente durante
  el desarrollo inicial y se recrearon con `migrate:fresh --seed` (`docs/decisions.md` D-08).
- **Regla por defecto a partir de ahora:** crear una migración **nueva** y no editar las existentes,
  salvo que el responsable autorice expresamente editar y recrear.
- PostgreSQL 16; el código usa `ILIKE` y `jsonb`.

## Entradas

Cambio de datos requerido, tablas y columnas afectadas, y si es aceptable perder los datos locales.

## Procedimiento

1. Aplicá primero la skill `analisis-impacto`.
2. Pedí autorización indicando qué migración vas a crear y si se ejecutará `migrate` o se requiere
   `migrate:fresh`.
3. Creá la migración: `wslc exec erp_backend php artisan make:migration <accion>_<tabla>_table`.
   Implementá `up()` y un `down()` reversible. Seguí las convenciones existentes:
   - tablas en plural y en español;
   - FK con `foreignId()->constrained()` y política `restrictOnDelete`/`cascadeOnDelete` coherente
     con `docs/architecture.md`;
   - `softDeletes()` en entidades de negocio;
   - índices para los filtros frecuentes.
4. Si agregás una restricción `unique` sobre una tabla con soft delete, considerá DEF-10 (un registro
   dado de baja bloquea el valor).
5. Actualizá el modelo (`$fillable`, `$casts`, relaciones), las reglas de validación de `store` y
   `update` en el controlador, `ExportService` si corresponde y el seeder, si los datos demo dependen
   del cambio.
6. Si agregás un modelo polimórfico, registralo en `AppServiceProvider::boot()` → `Relation::morphMap`.
7. Actualizá el frontend que lee o escribe esos campos y las etiquetas `fieldLabels` en
   `frontend/composables/useToast.ts`.
8. Ejecutá, solo con autorización: `wslc exec erp_backend php artisan migrate`. Después:
   `migrate:status`.
9. Agregá o ajustá tests en `backend/tests/Feature/` (corren sobre `erp_dpsit_test` con `RefreshDatabase`)
   y validá con la skill `validar-cambios` y probá `down()` únicamente si el responsable lo autoriza
   (`migrate:rollback --step=1`).
10. Actualizá `docs/architecture.md` (tabla del modelo de datos y diagrama), `docs/api.md` y registrá la
    decisión en `docs/decisions.md` si cambia una regla de negocio.

## Resultado esperado

Una migración nueva y reversible, con modelo, validación, seeders, frontend y documentación coherentes, y
el registro de qué comandos se ejecutaron sobre la base.

## Restricciones

- Nunca `migrate:fresh`, `db:wipe` ni `migrate:reset` sin autorización explícita.
- No ejecutar nada contra bases que no sean la local de desarrollo.
- No incluir datos personales reales en seeders. Excepción autorizada: las máximas autoridades de los
  organismos en `MinisterioSeeder` (`docs/decisions.md` D-32).
