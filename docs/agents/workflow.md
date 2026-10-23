# Flujo de trabajo con agentes

Aplica a cualquier agente (Claude Code, Codex u otro) y a las personas. Está pensado para un único
responsable que alterna entre dos computadoras y, eventualmente, entre varios agentes.

## 1. Iniciar una tarea

1. Leer `AGENTS.md` (Claude Code lo carga mediante `CLAUDE.md`; Codex lo lee directamente).
2. Leer `docs/agents/current-state.md`: defectos, preguntas pendientes y tareas en curso.
3. Ejecutar `git status`, `git branch --show-current` y `git log --oneline -5`. Si hay cambios sin
   confirmar que no son tuyos, **no los modifiques ni los descartes**: informalos y preguntá.
4. Confirmar el alcance con el responsable si la tarea es funcional, ambigua o toca algo de la lista de
   «Acciones que requieren autorización» de `AGENTS.md`.
5. Crear una rama desde `develop`: `feat/<tema>`, `fix/<tema>` o `docs/<tema>`.
6. Si la tarea durará más de una sesión o hay otro agente trabajando, registrarla en «Tareas en curso»
   de `current-state.md` (agente, rama y archivos que se van a tocar).
7. Para cambios funcionales, aplicar la skill `analisis-impacto` antes de editar.

Plantilla para anunciar el inicio (en el chat o en la descripción del PR):

```markdown
**Tarea:** <qué>
**Motivo / pedido:** <quién lo pidió y para qué>
**Rama:** <rama>
**Archivos previstos:** <lista>
**Riesgos / autorizaciones necesarias:** <migraciones, datos, dependencias…>
**Validación prevista:** <comandos y flujo manual>
```

## 2. Durante la tarea

- Cambios mínimos y acotados a la tarea; nada de refactorizaciones oportunistas.
- Seguir los patrones existentes (ver `docs/development.md` → «Convenciones de código»).
- Ante una duda de negocio, preguntar en lugar de inventar. Si no hay respuesta, registrarla en
  «Preguntas pendientes».

## 3. Cerrar una tarea

1. Ejecutar la skill `validar-cambios` y anotar **qué** se ejecutó, con qué resultado, y qué **no** se
   pudo ejecutar.
2. Ejecutar la skill `actualizar-documentacion`.
3. Agregar una fila al «Registro de tareas» de `current-state.md` y quitar la fila de «Tareas en curso».
4. Commit (solo si el responsable lo pidió), con Conventional Commits en español.
5. Reporte final con este formato:

```markdown
**Hecho:** <resumen>
**Archivos:** <creados / modificados>
**Validación:** <comando → resultado>; no ejecutado: <qué y por qué>
**Hechos vs. suposiciones:** <lo que no se pudo verificar>
**Pendientes / riesgos:** <…>
```

## 4. Git

- `main`: producción, solo mediante merge revisado desde `develop`. `develop`: integración.
- Ramas de trabajo cortas desde `develop`; integrarlas con PR o merge revisado por el responsable.
- Nunca: `push --force` a `develop`/`main`, reescribir historial publicado, ni commitear `.env`,
  `vendor/`, `node_modules/` o adjuntos.
- Al cambiar de computadora: `git pull` y, si hubo cambios de esquema, aplicar `migrate` (no `fresh`)
  salvo que el responsable acepte perder los datos locales.

## 5. Trabajo en paralelo (varios agentes)

**No** hay que suponer que dos agentes pueden trabajar a la vez sobre el mismo árbol de trabajo.

- Cada agente trabaja en su propia rama y en su propio **worktree**
  (`git worktree add ../erp-dpsit-<tema> -b feat/<tema> develop`) o en un clon separado.
- El entorno de contenedores es **uno solo** (puertos 3000/8000/5432 fijos, mismo volumen de base).
  Solo el árbol principal debe estar montado en los contenedores. Los demás worktrees se limitan a
  editar y leer código; la validación en ejecución se hace después del merge, en el árbol principal.
- Se paraleliza solo lo que no comparte archivos: dos módulos distintos, o documentación y código.
  Antes de empezar, declarar los archivos en «Tareas en curso». Si dos tareas comparten un archivo,
  se hacen en serie.
- Archivos de alta contención, que **requieren coordinación central** (un agente por vez):
  - `backend/routes/api.php`;
  - `backend/database/migrations/`;
  - `backend/app/Providers/AppServiceProvider.php` (morphMap);
  - `frontend/composables/useApi.ts`;
  - `frontend/layouts/default.vue`;
  - `docker-compose.yml`, `docker/`, `wslc-setup.ps1`, `.env.example`;
  - `AGENTS.md` y `docs/decisions.md`.
- Tareas aptas para delegar: análisis de solo lectura, búsqueda de usos, redacción de documentación,
  revisión de seguridad de un módulo, cambios aislados en una página Vue, creación de tests nuevos.
- Antes de integrar el trabajo de otro agente: leer el diff completo, verificar que no tocó archivos
  fuera de lo declarado, ejecutar `validar-cambios` en el árbol principal y revisar que actualizó la
  documentación.

## 6. Resolver contradicciones

Orden de prioridad:

1. indicación explícita del responsable en la sesión;
2. `AGENTS.md`;
3. `docs/` (el documento específico del tema);
4. comentarios en el código.

El código es la verdad sobre **lo que existe**; los documentos, sobre **lo que se decidió**. Si
divergen, no hay que «arreglar» el código para que coincida con el documento sin preguntar: se reporta
y se registra.

## 7. Plantillas de pedidos

Pedido de funcionalidad:

```markdown
**Módulo:** <ministerios / áreas TI / personas / roles / servicios / exportador / otro>
**Necesidad:** <qué debe poder hacer quién (perfil)>
**Criterios de aceptación:** <lista verificable>
**Fuera de alcance:** <…>
**Datos / migraciones:** <sí/no; ¿se pueden perder datos locales?>
```

Reporte de defecto:

```markdown
**Dónde:** <URL/pantalla o endpoint> · **Perfil:** <ADMIN/EDITOR/LECTOR> · **Entorno:** <WSLC/Linux>
**Pasos:** 1… 2… 3…
**Esperado:** <…> · **Obtenido:** <… mensaje de error sin datos personales>
**Logs:** `wslc logs erp_backend` / `backend/storage/logs/laravel.log` (extracto mínimo)
```

Decisión técnica: plantilla al final de `docs/decisions.md`.
