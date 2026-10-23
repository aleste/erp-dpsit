# Documentación de ERP DPSIT

| Documento | Para qué |
| :--- | :--- |
| [`../AGENTS.md`](../AGENTS.md) | Reglas de trabajo para agentes y personas (empezar aquí) |
| [`architecture.md`](architecture.md) | Componentes, autenticación, modelo de datos, infraestructura |
| [`api.md`](api.md) | Tabla de endpoints con perfiles y uso desde la UI |
| [`product.md`](product.md) | Requisitos originales recuperados e inventario de módulos con su estado |
| [`development.md`](development.md) | Entorno local (WSLC/Linux), variables, comandos, validación, problemas conocidos |
| [`security.md`](security.md) | Hallazgos de seguridad y controles obligatorios |
| [`decisions.md`](decisions.md) | Registro de decisiones (confirmadas y pendientes) |
| [`agents/current-state.md`](agents/current-state.md) | Estado actual, defectos, preguntas pendientes y registro de tareas |
| [`agents/workflow.md`](agents/workflow.md) | Cómo iniciar y cerrar tareas, Git, trabajo en paralelo, plantillas |
| [`agents/compatibility.md`](agents/compatibility.md) | Claude Code y Codex: mecanismos, skills, inventario de MCP y configuración encontrada |
| [`proposals/modulo-expedientes.md`](proposals/modulo-expedientes.md) | Relevamiento de la planilla y propuesta (sin implementar) del módulo de expedientes |
| [`proposals/modulo-firma-digital.md`](proposals/modulo-firma-digital.md) | Propuesta (sin implementar) del módulo de tokens de firma digital |
| [`agents/audit-report-2026-10-23.md`](agents/audit-report-2026-10-23.md) | Informe de la auditoría de transferibilidad |

Skills reutilizables: [`../.agents/skills/`](../.agents/skills/). Los adaptadores para Claude Code están
en `../.claude/skills/`.

Mantenimiento: cada dato vive en un solo documento; los demás enlazan. Al cerrar una tarea aplicá la
skill [`actualizar-documentacion`](../.agents/skills/actualizar-documentacion/SKILL.md).
