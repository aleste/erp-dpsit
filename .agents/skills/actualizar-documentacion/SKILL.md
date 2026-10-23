---
name: actualizar-documentacion
description: Mantiene coherentes la documentación y el estado del proyecto ERP DPSIT (docs/, AGENTS.md, current-state.md) después de un cambio. Usar al cerrar cualquier tarea que cambie código, configuración, decisiones o comportamiento.
---

# Actualizar documentación

## Procedimiento

1. Listá los archivos tocados (`git diff --name-only develop...HEAD` o `git status`).
2. Por cada tipo de cambio, actualizá **solo** el documento fuente de verdad que corresponde (ver la tabla
   «Fuentes de verdad» en `AGENTS.md`):

   | Si cambió… | Actualizar |
   | :--- | :--- |
   | rutas, parámetros o respuestas | `docs/api.md` |
   | tablas, relaciones, capas, infraestructura | `docs/architecture.md` |
   | comportamiento visible o estado de un módulo | `docs/product.md` (inventario) |
   | comandos, variables `.env`, scripts, problemas de entorno | `docs/development.md` |
   | autenticación, archivos, datos personales, dependencias | `docs/security.md` |
   | una regla de negocio o una elección técnica | `docs/decisions.md` (nueva fila D-NN) |
   | reglas de trabajo para agentes | `AGENTS.md` (mantener < 150 líneas) |
   | un procedimiento de skill | `.agents/skills/<skill>/SKILL.md` (no el adaptador de `.claude/`) |

3. En `docs/agents/current-state.md`:
   - marcá como resueltos los DEF/SEC corregidos (moverlos o anotar la fecha y el commit);
   - agregá los defectos nuevos;
   - quitá la tarea de «Tareas en curso»;
   - agregá una fila al «Registro de tareas».
4. Verificá los enlaces relativos que agregaste: que el archivo exista
   (`test -f <ruta>`).
5. Verificá que no copiaste secretos ni datos personales:
   `git diff | grep -iE "password|secret|APP_KEY=|token"` y revisá cada coincidencia.

## Restricciones

- No duplicar contenido entre documentos: enlazar.
- No convertir una recomendación en «decisión» sin la confirmación del responsable (Origen = Propuesta,
  Estado = Pendiente).
- No marcar un módulo como «verificado» sin una prueba de comportamiento ejecutada.
