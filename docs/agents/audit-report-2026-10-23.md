# Informe de auditoría de transferibilidad — 2026-10-23

Auditoría realizada por Claude Code (Opus 5.5) sobre la rama `develop` (commit `b26c67e`, con un cambio
sin confirmar en `wslc-setup.ps1`). Objetivo: recuperar el conocimiento disponible y preparar el
repositorio para que distintos agentes de IA continúen el desarrollo de forma consistente.

No se modificó lógica funcional, no se ejecutaron migraciones ni despliegues, no se actualizaron
dependencias y no se instalaron herramientas. No se hicieron commits.

## A. Diagnóstico inicial

- **Tecnologías**:
  - backend: Laravel 11.55.1 sobre PHP 8.3 (FPM, Alpine), con Sanctum 4.3;
  - base de datos: PostgreSQL 16;
  - frontend: Nuxt 3.21.11 (Vue 3.5, SPA sin SSR) con Pinia y Tailwind;
  - servidor web: Nginx;
  - entorno: WSLC 3.0 en Windows (en uso) y Docker Compose en Linux (sin verificar).

  Detalle en `docs/architecture.md`.
- **Organización**: monorepo con `backend/` y `frontend/`, scripts de entorno en la raíz y `docker/`.
  117 archivos versionados y 6 commits de un único autor.
- **Documentación previa**: solo `README.md` (instalación y credenciales demo). No había documentación
  de arquitectura, de la API, de decisiones ni del estado del proyecto. El código casi no tiene
  comentarios y no contiene TODO/FIXME.
- **Configuración de agentes previa en el repo**: ninguna.
- **Riesgos para la continuidad**:
  1. Conocimiento clave (requisitos y decisiones) disponible solo en las transcripciones locales del
     agente anterior.
  2. Ausencia total de tests.
  3. Los scripts de arranque destruyen la base de datos.
  4. Dos definiciones del stack (WSLC y Compose) que divergen.
  5. Endpoints públicos que exponen datos personales.

## B. Conocimiento recuperado

- **Agente anterior identificado**: Google Antigravity (CLI e IDE) con modelos Gemini Flash. Hay tres
  conversaciones relevantes en `~/.gemini/antigravity-cli/brain/` y `~/.gemini/antigravity-ide/brain/`,
  con transcripciones completas (detalle en `docs/agents/compatibility.md`).
- **Recuperado y documentado**:
  - requisitos funcionales originales (`docs/product.md`);
  - 15 decisiones (`docs/decisions.md`): 9 confirmadas por el responsable, 1 de práctica observada
    (D-08) y 5 deducidas del código, más 1 propuesta de esta auditoría (D-16);
  - historial de problemas del entorno y sus soluciones (`docs/development.md`);
  - preferencias de trabajo: el responsable autorizó escribir sin pedir permiso en agosto, pero en
    sesiones posteriores pidió «no ejecutes código sin confirmación» y elegir entre opciones analizadas;
  - estimación de requisitos de producción (VMware y Ubuntu Server 24.04, 4 vCPU, 8 GB, HTTPS,
    backups), que **no** está implementada.
- **Skills y configuración de agentes detectadas**: un subagente de Antigravity
  (`ui_notification_specialist`), obsoleto. `mcp_config.json` de Antigravity está vacío. No hay MCP del
  proyecto.
- **No recuperable con los recursos disponibles**:
  - el razonamiento detrás de las decisiones no discutidas en el chat (D-11 a D-15);
  - si el entorno Linux llegó a probarse en la otra computadora;
  - quién hizo el commit `b26c67e` y cómo se validó;
  - las configuraciones locales de la otra computadora;
  - el contenido de las bases `*.db` de Antigravity (no se inspeccionaron; las transcripciones JSONL
    bastaron);
  - si existen requisitos formales fuera de lo conversado (normativa, expedientes, etc.).

## C. Cambios realizados

Archivos **nuevos**:

| Ruta | Finalidad | Contenido principal |
| :--- | :--- | :--- |
| `AGENTS.md` | Instrucciones comunes para todos los agentes (Codex lo lee nativamente) | Mapa del repo, fuentes de verdad, ejecución, convenciones, acciones que requieren autorización, controles de seguridad, cierre de tareas, resolución de contradicciones |
| `CLAUDE.md` | Adaptador para Claude Code | `@AGENTS.md` y notas propias de Claude |
| `docs/README.md` | Índice de la documentación | Tabla de documentos |
| `docs/architecture.md` | Arquitectura basada en el código | Diagramas Mermaid (componentes, login, ER), capas, autorización, persistencia, comparación WSLC/Compose |
| `docs/api.md` | Contrato de la API | Las 48 rutas con perfil, filtros y uso desde la UI |
| `docs/product.md` | Descripción funcional | Requisitos originales recuperados e inventario de módulos con estados |
| `docs/development.md` | Guía reproducible | Variables, WSLC, Linux, problemas conocidos, validación disponible con sus resultados, convenciones |
| `docs/security.md` | Revisión preliminar de seguridad | SEC-01 a SEC-12, controles obligatorios, requisitos para producción |
| `docs/decisions.md` | Registro de decisiones | D-01 a D-16 con origen y estado, y plantilla |
| `docs/agents/current-state.md` | Estado vivo | Resumen, DEF-01 a DEF-16, pendientes, 11 preguntas, tareas en curso, registro de tareas |
| `docs/agents/workflow.md` | Procedimiento multiagente | Inicio y cierre de tareas, Git, paralelismo con worktrees, archivos de alta contención, plantillas |
| `docs/agents/compatibility.md` | Compatibilidad e inventarios | Claude Code frente a Codex (verificado en la documentación oficial), estrategia, catálogo de skills, configuración de agentes encontrada, inventario MCP |
| `docs/agents/audit-report-2026-10-23.md` | Este informe | — |
| `.agents/skills/{analisis-impacto,validar-cambios,cambio-esquema-bd,actualizar-documentacion}/SKILL.md` | Skills canónicas (formato que Codex descubre) | Procedimientos con entradas, pasos, resultado y restricciones |
| `.claude/skills/<las mismas 4>/SKILL.md` | Adaptadores para Claude Code | Mismo `name`/`description`, remiten al archivo canónico |

Archivos **existentes modificados**:

| Ruta | Cambio | Motivo |
| :--- | :--- | :--- |
| `README.md` | 3 líneas al inicio: enlace a `docs/` y `AGENTS.md`, advertencia sobre `migrate:fresh` | Punto de entrada para humanos y agentes; riesgo de pérdida de datos |
| `.gitignore` | Bloque «AI agents»: `CLAUDE.local.md`, `.claude/settings.local.json`, `.claude/worktrees/`, `AGENTS.override.md` | Evitar versionar configuración personal |

No se tocó `wslc-setup.ps1`: su cambio pendiente es del agente anterior y se dejó intacto.

## D. Compatibilidad multiagente

- **Codex**: lee `AGENTS.md` desde la raíz del repositorio y descubre `.agents/skills/`. No requiere
  `.codex/`, porque no hay MCP que configurar. **No se probó**: Codex no está instalado en esta
  máquina.
- **Claude Code**: carga `CLAUDE.md`, que importa `AGENTS.md`, y descubre `.claude/skills/`. Las skills
  nuevas pueden requerir abrir una sesión nueva. Para confirmar la carga, ejecutá `/context` y revisá
  «Memory files».
- **Otros agentes** (Antigravity, Cursor, Copilot): no se crearon adaptadores porque no se decidió
  usarlos. Si se adopta uno, su adaptador debe limitarse a remitir a `AGENTS.md`.
- **Sin validar**:
  - la carga real de las instrucciones y las skills por Codex;
  - el descubrimiento de las skills de Claude en una sesión limpia;
  - los subagentes de Codex.

## E. Validaciones

Ejecutadas (todas de solo lectura sobre el entorno local):

| Verificación | Resultado |
| :--- | :--- |
| `php -l` en `app`, `routes`, `config`, `database` y `bootstrap` | sin errores |
| `vendor/bin/pint --test` | 5 archivos con diferencias de estilo |
| `php artisan route:list --path=api` | 48 rutas, coincide con `docs/api.md` |
| `php artisan migrate:status` | 12/12 aplicadas |
| `php artisan test` | falla: no hay `phpunit.xml` (DEF-07) |
| `GET /` (8000) y `GET /` (3000) | 200 / 200 |
| `GET /api/dashboard/stats` sin token | 401 |
| `GET /api/documentos/999999/descargar` sin token | 404 en lugar de 401 → confirma SEC-01 |
| `composer audit`, `npm audit --omit=dev` | avisos en 4 paquetes PHP; 25 en JS (detalle en `docs/security.md`) |
| Conteo de filas en la base local | coincide con lo documentado en `docs/product.md` |
| Enlaces relativos y rutas citadas en los documentos nuevos | todos válidos. Las rutas inexistentes mencionadas son intencionales (archivos que no existen o que se proponen) |
| Búsqueda de secretos en los documentos nuevos | sin valores de `.env` ni `APP_KEY`; la contraseña demo no se reproduce |
| Comandos documentados frente a scripts y `Makefile` | revisados contra los archivos; `wslc logs --tail` verificado con `--help` |

**No ejecutado**: Docker Compose y Linux (no hay Docker en el host), flujos de la UI en el navegador,
login con usuarios demo, `GET /api/export/download` (escribe en la auditoría y expone datos personales),
`nuxi typecheck` (no está instalado) y Codex.

**Prueba de transferencia**: ver la sección H.

## F. Riesgos y decisiones pendientes

Requieren atención humana, en orden de prioridad:

1. **SEC-01**: descargas y exportación sin autenticación. Corregirlo implica tocar backend y frontend.
2. **DEF-02**: los scripts de arranque recrean la base.
3. **DEF-01**: nginx frente a Compose; además, confirmar el cambio pendiente de `wslc-setup.ps1`.
4. Política de migraciones (D-08).
5. Sin tests (DEF-07). Se recomienda empezar con tests de *feature* de la API para autorización y
   vigencias.
6. `APP_KEY` y contraseñas versionadas (SEC-02, SEC-03) antes de cualquier entorno compartido.
7. Aprobar la estructura de agentes (D-16) y decidir si se versiona.

Lista completa de preguntas en `docs/agents/current-state.md`.

> **Actualización del 2026-10-23, posterior al informe.** El responsable respondió tres puntos:
> - SEC-01 no se corrige por ahora y no se tocan el backend ni el frontend (D-17);
> - el cambio de `wslc-setup.ps1` queda confirmado (D-18);
> - los datos del seed son ficticios.
>
> Los puntos 1 y 3 de la lista anterior se resuelven así: el punto 1 queda pospuesto y del punto 3 se
> confirmó la parte de WSLC; la de nginx sigue abierta.

## G. Instrucciones para el siguiente agente

1. Leé `AGENTS.md` (en Claude Code, se carga mediante `CLAUDE.md`).
2. Leé `docs/agents/current-state.md` y `docs/agents/workflow.md` §1.
3. Ejecutá `git status`, `git branch --show-current` y `git log --oneline -5`. Si sigue el cambio sin
   confirmar en `wslc-setup.ps1` u otros archivos ajenos, no los toques.
4. Comprobá el entorno con `wslc ps`. **No** ejecutes `wslc-setup.ps1` para «levantarlo»: borra la
   base.
5. Resumí tu comprensión y confirmá el alcance con el responsable antes de modificar archivos.

Prompt inicial sugerido:

```text
Vas a continuar el desarrollo del ERP DPSIT en C:\Proyectos\erp-dpsit.
Antes de modificar cualquier archivo:
1. Leé AGENTS.md y seguí sus reglas. Después leé docs/agents/current-state.md y docs/agents/workflow.md.
2. Ejecutá git status, git branch --show-current y git log --oneline -5, e informame si hay cambios sin
   confirmar o algo inesperado.
3. Comprobá con `wslc ps` si los contenedores están en ejecución (no ejecutes wslc-setup.ps1).
4. Resumime en no más de 10 líneas qué es el sistema, el estado actual, los 3 riesgos principales y las
   preguntas pendientes que afectan a la tarea.
5. Esperá mi confirmación antes de editar.
La tarea es: <describir la tarea>.
```

## H. Prueba de transferencia

Ejecutada el 2026-10-23 con un subagente de Claude Code de **contexto limpio**, en modo solo lectura.
No es un agente de otro proveedor, así que no reemplaza una prueba con Codex.

Instrucción que recibió: leer solo `AGENTS.md` y `docs/`, y analizar sin implementar este pedido
hipotético: «el exportador debe filtrar también por categoría de servicio e incluir a los referentes de
servicios». Recién al final podía abrir hasta 3 archivos de código.

**Resultado:**

- identificó los archivos de backend y frontend, los endpoints y su perfil, y los riesgos aplicables
  (SEC-01, SEC-08, DEF-04, DEF-07, DEF-08);
- identificó las autorizaciones necesarias, los comandos exactos de validación y la secuencia de skills;
- respetó las restricciones: no llamó a `/export/download` y distinguió «compiló» de «probado».

**Información que faltó y se corrigió:**

- `docs/api.md` decía que la descarga CSV usa «los mismos filtros» que la vista previa. No es así:
  faltan `incluir_alternativos` y la validación.
- No estaba documentado que la vista previa no se audita.
- Faltaban los valores de `catalogo_servicios.categoria`.
- El exportador no incluye a los referentes de servicios. Se reclasificó como PARCIAL en
  `docs/product.md`.

**Defectos nuevos registrados** a partir de la prueba:

- DEF-15: el CSV ignora los filtros desmarcados, porque el frontend omite los `false` y el backend asume
  `true` (confirmado leyendo el código de ambos lados);
- DEF-16: `ServicioReferente` se importa pero no se usa.

**Preguntas de negocio que siguen abiertas** (agregadas como n.º 11 en `current-state.md`):

- ¿«SEGURIDAD» se refiere a la categoría de servicio o al tipo de referente?
- ¿Qué cuenta como vigencia de un referente?
- ¿Los filtros se combinan por unión o por intersección?
- ¿Se deduplica por persona?
