# Compatibilidad entre agentes, skills y MCP

Verificado el 2026-10-23 contra la documentación oficial:

- Claude Code: [memoria e instrucciones](https://code.claude.com/docs/en/memory) y
  [skills](https://code.claude.com/docs/en/skills).
- Codex: [AGENTS.md](https://learn.chatgpt.com/docs/agent-configuration/agents-md),
  [skills](https://developers.openai.com/codex/skills) y
  [MCP](https://learn.chatgpt.com/docs/extend/mcp?surface=cli).

En esta máquina hay instalado Claude Code 2.1.309. **Codex no está instalado**, así que su
configuración no se probó en ejecución.

## Cómo descubre cada agente las instrucciones

| Aspecto | Claude Code | Codex |
| :--- | :--- | :--- |
| Archivo de proyecto | `CLAUDE.md` o `.claude/CLAUDE.md`; lee `AGENTS.md` de forma nativa (v2.1.277+) **solo si no hay `CLAUDE.md`/`CLAUDE.local.md`** | `AGENTS.md` (antes, `AGENTS.override.md` en cada directorio) |
| Subdirectorios | carga el `CLAUDE.md` del subdirectorio al leer o editar archivos allí | concatena desde la raíz del repo hasta el directorio actual; un archivo por directorio |
| Importar otros archivos | `@ruta` dentro de `CLAUDE.md` (hasta 4 niveles) | no documentado |
| Reglas por ruta | `.claude/rules/*.md` con frontmatter `paths:` | `AGENTS.md` anidados por directorio |
| Personal, no versionado | `CLAUDE.local.md`, `~/.claude/CLAUDE.md` | `~/.codex/AGENTS.md` (o `AGENTS.override.md`) |
| Límite | recomendado < 200 líneas por archivo | `project_doc_max_bytes` = 32 KiB combinados |
| Skills de proyecto | `.claude/skills/<nombre>/SKILL.md`; **no lee `.agents/`** | `.agents/skills/<nombre>/SKILL.md` (del directorio actual hacia la raíz) |
| Skills de usuario | `~/.claude/skills/` | `~/.agents/skills/` |
| Formato de skill | `SKILL.md` con frontmatter; `description` recomendado, `name` opcional | `SKILL.md` con `name` y `description` |
| MCP de proyecto | `.mcp.json` en la raíz | `.codex/config.toml` → `[mcp_servers.<nombre>]` (solo proyectos de confianza) |
| MCP de usuario | `~/.claude.json` (`claude mcp add`) | `~/.codex/config.toml` (`codex mcp add`) |
| Subagentes | `.claude/agents/*.md`, aislamiento con worktrees | página «Subagents» en su documentación; **no revisada** en esta auditoría |

## Estrategia adoptada (propuesta D-16)

1. **Conocimiento estable**: `docs/`, una sola copia.
2. **Instrucciones generales**: `AGENTS.md`. Codex lo lee sin configuración.
3. **Adaptador de Claude Code**: `CLAUDE.md`, que contiene `@AGENTS.md` y unas pocas notas propias. Es
   necesario porque, si existe `CLAUDE.md` (por ejemplo, porque `/init` lo crea), Claude deja de leer
   `AGENTS.md`. La importación funciona en cualquier versión.
4. **Skills**: la fuente común es `.agents/skills/<nombre>/SKILL.md`, que Codex descubre nativamente.
   `.claude/skills/<nombre>/SKILL.md` es un adaptador de pocas líneas con el mismo `name`/`description`,
   que ordena leer y seguir el archivo de `.agents/`. No se usan symlinks porque Git en Windows los
   maneja mal; ya hubo errores con symlinks en este repo.
5. **Reglas por tecnología**: por ahora están dentro de `AGENTS.md` y `docs/development.md`. El proyecto
   es chico y no justifica `.claude/rules/` ni `AGENTS.md` anidados. Si `AGENTS.md` supera ~150 líneas,
   crear `backend/AGENTS.md` y `frontend/AGENTS.md` (los leen ambos agentes al trabajar ahí; en Claude,
   solo si no hay `CLAUDE.md` en ese subdirectorio).
6. **Contexto temporal**: `docs/agents/current-state.md` y el chat. Nunca dentro de `AGENTS.md`.
7. **Configuración local** (no versionar): `CLAUDE.local.md`, `.claude/settings.local.json`,
   `AGENTS.override.md`. Se agregaron a `.gitignore`.

Al editar una skill, solo cambia `.agents/skills/…`. Si cambiás su `description`, actualizá también la del
adaptador en `.claude/skills/…`.

## Catálogo de skills del proyecto

| Skill | Objetivo | Cuándo usarla | Riesgo |
| :--- | :--- | :--- | :--- |
| `analisis-impacto` | Mapear qué toca un cambio (rutas, controlador, modelo, migración, páginas, docs) antes de editar | antes de cualquier cambio funcional | solo lectura |
| `validar-cambios` | Ejecutar las verificaciones disponibles y reportar resultados reales | al cerrar cualquier tarea de código | solo lectura |
| `cambio-esquema-bd` | Procedimiento seguro para modificar tablas y migraciones | cuando cambia la base | escribe migraciones; requiere autorización para ejecutarlas |
| `actualizar-documentacion` | Mantener `docs/` y `current-state.md` coherentes con el cambio | al cerrar cualquier tarea | solo documentación |

Candidatas no creadas (crearlas cuando haya necesidad real):

- `revision-seguridad` (hoy alcanza con `docs/security.md`);
- `nuevo-modulo-crud` (cuando se implemente el siguiente ABM);
- `preparar-release` (no hay producción).

## Configuración de agentes encontrada

| Ruta | Herramienta | Finalidad | Estado | Acción recomendada |
| :--- | :--- | :--- | :--- | :--- |
| *(repo)* | — | No había `AGENTS.md`, `CLAUDE.md`, `.claude/`, `.codex/`, `.cursor/`, `.github/` ni configuración MCP | — | estructura creada en esta auditoría |
| `~/.gemini/antigravity-cli/brain/da3dc7c7-…/` | Antigravity CLI | Conversación principal de desarrollo (2026-08-19 a 2026-08-24). Contiene la transcripción completa | inactiva | Conocimiento extraído a `docs/product.md`, `docs/decisions.md` y `docs/development.md`. Conservar fuera del repo: contiene credenciales demo |
| `~/.gemini/antigravity-cli/brain/898d1396-…/` | Antigravity CLI | Sesión del sistema de toasts y modales (2026-08-19) | inactiva | ídem |
| `~/.gemini/antigravity-cli/brain/da3dc7c7-…/.agents/agents/ui_notification_specialist/agent.md` | Antigravity | Subagente «ui_notification_specialist»: reemplazar `alert`/`confirm` por toasts | obsoleto (la tarea está terminada) | No migrar; su regla quedó en `AGENTS.md` |
| `~/.gemini/antigravity-ide/brain/7ff67da6-…/` | Antigravity IDE | Sesión del 2026-10-23: política de PowerShell y alias de red | reciente | Su cambio en `wslc-setup.ps1` sigue sin confirmar |
| `~/.gemini/config/mcp_config.json` | Antigravity | Configuración MCP | **vacío** (0 bytes) | ninguna |
| `~/.gemini/config/plugins/` | Antigravity | Plugins `android-cli-plugin`, `firebase`, `modern-web-guidance-plugin` | instalados a nivel usuario; sin uso visible en el proyecto | ninguna |
| `~/.claude/projects/C--Proyectos-erp-dpsit/memory/` | Claude Code | Memoria automática del proyecto | vacía | — |

## Inventario MCP

**No hay servidores MCP configurados para este proyecto**: no existe `.mcp.json` ni `.codex/config.toml`,
y el `mcp_config.json` de Antigravity está vacío.

A nivel de usuario, la sesión de Claude Code de esta auditoría tiene conectores propios del responsable
(entre otros, uno de expedientes GDE y otros de Google y del navegador). **No tienen relación con este
proyecto, no se usaron y no deben configurarse en el repositorio.**

Integraciones que podrían justificarse más adelante, si aparece la necesidad (no se instaló nada):

| Posible integración | Para qué | Requisitos y riesgos |
| :--- | :--- | :--- |
| MCP de PostgreSQL de **solo lectura** | Consultar el esquema y los datos locales sin `psql` manual | Usuario de base con permisos solo `SELECT`; nunca contra datos reales |
| MCP de GitHub | PRs e issues | Token con alcance mínimo; preferir el `gh` CLI, que ya cubre el caso |
| Navegador (Playwright o similar) | Pruebas manuales de la UI | Solo contra `localhost` |

## Pendiente de validar

- Que Codex cargue `AGENTS.md` y descubra `.agents/skills/` en este repo (Codex no está instalado).
- La detección de las skills de `.claude/skills/` en una sesión nueva de Claude Code (las skills nuevas
  pueden requerir reiniciar la sesión).
- Subagentes de Codex.
