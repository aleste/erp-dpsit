# CLAUDE.md — adaptador para Claude Code

Las instrucciones del proyecto son comunes a todos los agentes y están en `AGENTS.md`. Se importa aquí
para que Claude Code las cargue siempre (un `CLAUDE.md` presente desactiva la lectura nativa de
`AGENTS.md`, así que la importación es necesaria):

@AGENTS.md

## Específico de Claude Code

- Skills: `.claude/skills/<nombre>/SKILL.md` son adaptadores mínimos que remiten a
  `.agents/skills/<nombre>/SKILL.md`. Si cambiás un procedimiento, editá **solo** la versión de `.agents/`.
- Preferencias personales no versionadas: `CLAUDE.local.md` (ignorado por Git).
- Shell en Windows: el host no tiene `php`, `composer`, `docker` ni `make`; los comandos de backend van
  por `wslc exec erp_backend ...`.
- Tareas paralelas con subagentes que editen archivos: usar aislamiento por worktree y respetar
  `docs/agents/workflow.md`.
