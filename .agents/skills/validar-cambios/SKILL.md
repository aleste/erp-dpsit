---
name: validar-cambios
description: Ejecuta las verificaciones no destructivas disponibles en ERP DPSIT (lint PHP, Pint, rutas, estado de migraciones, sondeos HTTP, compilación de Nuxt) y reporta resultados reales. Usar al cerrar cualquier cambio de código.
---

# Validar cambios

El proyecto solo tiene tests automatizados para el módulo de firma digital (`backend/tests/Feature/FirmaDigital`).
Esta skill ejecuta lo que existe y obliga a reportar con honestidad qué quedó sin verificar.

## Requisitos

- Contenedores en ejecución (`wslc ps` en Windows; `docker compose ps` en Linux).
- En Windows, los comandos de PHP van por `wslc exec erp_backend …`. En Linux, reemplazá ese prefijo por
  `docker compose exec backend …`.

## Procedimiento

1. Revisá el alcance real del cambio: `git status` y `git diff --stat`.
2. Si el cambio toca el backend:
   - Sintaxis de cada archivo PHP modificado: `wslc exec erp_backend php -l <ruta relativa a backend/>`.
   - Estilo, solo en los archivos tocados: `wslc exec erp_backend vendor/bin/pint --test <archivos>`.
     Cinco archivos ya tenían diferencias antes; no los corrijas si no son parte de la tarea.
   - Si cambiaron las rutas: `wslc exec erp_backend php artisan route:list --path=api` y confirmá
     método, URI y middleware.
   - Si cambió la configuración: `wslc exec erp_backend php artisan optimize:clear` seguido de
     `wslc exec erp_backend php artisan package:discover` (sin el segundo, la API puede fallar con
     «Failed to fetch»).
   - Estado de la base: `wslc exec erp_backend php artisan migrate:status`.
   - Tests: `wslc exec erp_backend php artisan test`. Usan la base `erp_dpsit_test`; si no existe, ver
     `docs/development.md` → «Tests». Si tocaste firma digital, personas o el envío de correo, deben pasar
     todos. Si agregás comportamiento nuevo, agregá tests en `backend/tests/Feature/`.
   - Tareas programadas: `wslc exec erp_scheduler php artisan schedule:list`; correo: buzón de Mailpit en
     http://localhost:8025.
3. Sondeos HTTP:
   - `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/` debe dar 200.
   - Un endpoint protegido sin token debe dar 401.
   - Para endpoints nuevos o modificados, probá con token. Para obtenerlo: `POST /api/auth/login` con un
     usuario demo de `UserSeeder`. No pegues el token ni datos personales en el reporte.
4. Si el cambio toca el frontend:
   - `wslc logs --tail 30 erp_frontend` (Linux: `docker compose logs --tail 30 frontend`) no debe mostrar errores de Vite ni de Nitro.
   - `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/` debe dar 200.
   - Verificá que no se reintrodujeron diálogos nativos:
     `grep -rnE "\balert\(|[^a-zA-Z]confirm\(" frontend/pages frontend/components | grep -v confirmDialog`
     no debe devolver nada.
5. Prueba funcional manual, si hay navegador disponible: recorré el flujo con el perfil afectado y con un
   perfil que **no** debería tener acceso.
6. Si el cambio toca autenticación, archivos, exportación o datos personales, revisá `docs/security.md`.

## Resultado esperado

```markdown
**Validación ejecutada:**
- <comando> → <resultado>
**No ejecutado / no verificable:** <qué y por qué>
**Conclusión:** compila / responde / probado manualmente con <perfil y flujo> / no probado
```

## Restricciones

- No ejecutar `migrate:fresh`, `db:seed`, `wslc-setup.ps1`, `setup.sh` ni `make fresh` para «validar».
- No ejecutar `vendor/bin/pint` sin `--test` sobre archivos ajenos a la tarea.
- No declarar «probado» lo que solo compiló.
- No llamar a `GET /api/export/download` ni a descargas reales salvo que sea necesario: escriben en la
  auditoría y exponen datos personales.
