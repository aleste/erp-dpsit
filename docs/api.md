# Contrato de la API

Base: `http://localhost:8000/api`. Fuente de verdad: `backend/routes/api.php`. Para verlo en vivo:
`wslc exec erp_backend php artisan route:list --path=api` (86 rutas al 2026-10-23: 16 de firma digital, 19 de expedientes y 3 de usuarios).

Convenciones de respuesta:

- Éxito: `{ "success": true, "message"?: string, "data": ..., "stats"?: {...} }`.
- Validación: 422 estándar de Laravel `{ message, errors: { campo: [..] } }`, con mensajes en español
  (`backend/lang/es/validation.php`).
- Regla de negocio violada: 422 `{ success: false, message }`.
- Sin token o usuario inactivo: 401; sin el rol de módulo: 403 `{ success:false, message, modulo, rol_requerido }`;
  operación de administrador: 403 `{ success:false, message }`.
- Listados paginados (`personas`, `asignaciones-rol`, `servicios-areas`) devuelven el paginador de Laravel
  dentro de `data` (`data.data`, `data.total`, …) y aceptan `per_page` **sin tope máximo**.

Leyenda de permiso (D-34): **Pública** = sin `auth:sanctum` · **Auth** = cualquier usuario autenticado ·
**MÓDULO: C / O** = rol CONSULTA / OPERADOR o superior en ese módulo (con varios módulos, basta uno; middleware
`modulo:`) · **A** = solo ADMINISTRADOR, que además tiene el rol máximo en todos los módulos. «UI» indica si alguna página del frontend usa el endpoint.

| Método | Ruta | Perfil | Controlador@método | Filtros / notas | UI |
| :--- | :--- | :--- | :--- | :--- | :--- |
| POST | `/auth/login` | Pública | Auth@login | email, password → token | sí |
| GET | `/auth/user` | Auth | Auth@user | usuario + persona, `modulos` (rol efectivo por módulo) y `catalogo_modulos`; lo usa el frontend para refrescar permisos | no |
| POST | `/auth/logout` | Auth | Auth@logout | revoca token actual | sí |
| GET | `/dashboard/stats` | Auth (bloques por módulo) | Dashboard@stats | solo los bloques de los módulos habilitados (D-34): ORGANISMOS → ministerios, áreas, asignaciones y últimas 5; PADRON → personas; SERVICIOS → servicios y distribución por tipo | sí |
| POST | `/export/preview` | EXPORTADOR: C | Export@preview | `ministerio_ids[]`, `area_ti_ids[]`, `tipo_rol_ids[]`, `solo_vigentes`, `solo_maximas_autoridades`, `solo_homologadas`, `incluir_alternativos` (sin validación; solo `$request->only`). Fuente: solo `asignaciones_rol`, **no** incluye a los referentes de servicios. No se audita | sí |
| GET | `/export/download` | EXPORTADOR: C | Export@downloadCsv | mismos filtros que preview **salvo** `incluir_alternativos`, por query (`tipo_rol_ids=1,2`, booleanos como `1`; sin validación ni `filter_var`). `solo_vigentes` y `solo_homologadas` valen `true` si se omiten (ver DEF-15); CSV `;` con BOM UTF-8 | sí (descarga con token) |
| GET | `/documentos/{id}/descargar` | módulo del documento: C | DocumentoAdjunto@download | archivo binario; el módulo lo fija el tipo de entidad (area_ti y asignacion_rol → ORGANISMOS, servicio_area → SERVICIOS, persona → PADRON) | sí (descarga con token) |
| POST | `/documentos/upload` | módulo del documento: O | DocumentoAdjunto@upload | multipart: `documentable_type` ∈ area_ti/servicio_area/asignacion_rol/persona, `documentable_id`, `tipo_documento`, `titulo`, `archivo` (≤20 MB), `observaciones` | sí (solo área TI) |
| DELETE | `/documentos/{id}` | A | DocumentoAdjunto@destroy | soft delete; el archivo físico queda | no |
| GET | `/ministerios` | ORGANISMOS, SERVICIOS, PADRON, FIRMA_DIGITAL, EXPEDIENTES o EXPORTADOR: C | Ministerio@index | `q`, `activo`; incluye `autoridad_vigente.persona` | sí |
| GET | `/ministerios/{id}` | ORGANISMOS, SERVICIOS, PADRON, FIRMA_DIGITAL, EXPEDIENTES o EXPORTADOR: C | Ministerio@show | con áreas (y su autoridad vigente), `autoridad_vigente` y el historial `autoridades` del organismo | sí |
| POST/PUT | `/ministerios[/{id}]` | ORGANISMOS: O | Ministerio@store/update | `codigo`, `nombre`, `sigla`, `orden_prioridad`, `activo` | sí |
| DELETE | `/ministerios/{id}` | A | Ministerio@destroy | 422 si tiene áreas | sí |
| POST | `/ministerios/{id}/autoridad` | ORGANISMOS: O | AutoridadOrganismo@designar | `persona_id`, `cargo?`, `fecha_inicio?`, `acto_administrativo?`, `observaciones?`; cierra la vigente el día anterior; 422 si es la misma persona (D-32) | sí |
| PUT | `/autoridades-organismo/{id}` | ORGANISMOS: O | AutoridadOrganismo@update | corrige `cargo`, fechas, acto y observaciones (no la persona) | sí |
| DELETE | `/autoridades-organismo/{id}` | A | AutoridadOrganismo@destroy | baja lógica de una designación errónea | sí |
| GET | `/areas-ti` | ORGANISMOS, SERVICIOS o PADRON: C | AreaTi@index | `ministerio_id`, `es_homologada`, `nivel_jerarquico`, `q`; devuelve `stats` | sí |
| GET | `/areas-ti/{id}` | ORGANISMOS, SERVICIOS o PADRON: C | AreaTi@show | expediente: asignaciones, servicios, referentes, documentos | sí |
| POST/PUT | `/areas-ti[/{id}]` | ORGANISMOS: O | AreaTi@store/update | — | sí |
| POST | `/areas-ti/{id}/maxima-autoridad` | ORGANISMOS: O | AreaTi@designarAutoridad | `AutoridadAreaService` (D-33): cierra las autoridades abiertas o con cese futuro con cese el día anterior a `fecha_inicio`; 422 en `persona_id` si ya es la autoridad vigente y en `fecha_inicio` si no es posterior al inicio de la vigente | sí |
| DELETE | `/areas-ti/{id}` | A | AreaTi@destroy | 422 si tiene servicios | sí |
| GET | `/personas` | PADRON, ORGANISMOS, SERVICIOS o FIRMA_DIGITAL: C | Persona@index | `q` (apellido/nombre/CUIL/emails/título), `ministerio_id`, `area_ti_id`, `tipo_rol_id`, `solo_autoridades`, `estado_cargo` ∈ VIGENTE/SIN_CARGO, `per_page` (20); `stats` | sí |
| GET | `/personas/{id}` | PADRON, ORGANISMOS, SERVICIOS o FIRMA_DIGITAL: C | Persona@show | historial completo de cargos y referencias | sí |
| POST/PUT | `/personas[/{id}]` | PADRON o FIRMA_DIGITAL: O | Persona@store/update | obligatorios solo `apellido` y `nombre` (D-19); `cuil_cuit`, `email_principal`, `telefono_principal` opcionales; `tipo_documento` ∈ DNI/PASAPORTE/CI/LC/LE/OTRO y `numero_documento` van juntos (único por tipo); `q` del listado también busca por documento; `show` incluye `entregas_token` | sí |
| DELETE | `/personas/{id}` | PADRON o FIRMA_DIGITAL: O | Persona@destroy | Administrador: 422 si tiene asignaciones vigentes o es autoridad vigente. Operador (D-35): 422 si la persona tiene **cualquier** vínculo (cargos, referencias, autoridades, entregas de tokens, usuario o adjuntos) | sí |
| GET | `/tipos-rol` | ORGANISMOS, PADRON o EXPORTADOR: C | TipoRol@index | `activo`, `nivel_responsabilidad`; cuenta asignaciones vigentes | sí |
| GET | `/tipos-rol/{id}` | ORGANISMOS, PADRON o EXPORTADOR: C | TipoRol@show | — | no |
| POST/PUT/DELETE | `/tipos-rol[/{id}]` | A | TipoRol@store/update/destroy | borrar: 422 si tiene asignaciones | **no** |
| GET | `/asignaciones-rol` | ORGANISMOS o PADRON: C | AsignacionRol@index | `tipo_rol_id`, `ministerio_id`, `area_ti_id`, `persona_id`, `estado` ∈ VIGENTE/HISTORICO, `solo_maximas_autoridades`, `q`, `per_page` (25) | sí |
| GET | `/asignaciones-rol/{id}` | ORGANISMOS o PADRON: C | AsignacionRol@show | — | no |
| POST/PUT | `/asignaciones-rol[/{id}]` | ORGANISMOS: O | AsignacionRol@store/update | `es_maxima_autoridad` por defecto según el tipo de rol; si es máxima autoridad y no tiene `fecha_fin`, aplica las reglas de D-33 (mismo cierre y mismos 422 que `/areas-ti/{id}/maxima-autoridad`) | sí |
| POST | `/asignaciones-rol/{id}/finalizar-vigencia` | ORGANISMOS: O | AsignacionRol@finalizarVigencia | `fecha_fin` (422 si es anterior a `fecha_inicio`), `motivo` (máx. 500) y `acto_administrativo` (máx. 150) opcionales; se agrega a observaciones «Baja de cargo · motivo · Acto: …» | sí |
| DELETE | `/asignaciones-rol/{id}` | A | AsignacionRol@destroy | para cargas erróneas | sí |
| GET | `/catalogo-servicios` | SERVICIOS u ORGANISMOS: C | CatalogoServicio@index | `activo`, `categoria` | sí |
| GET | `/catalogo-servicios/{id}` | SERVICIOS u ORGANISMOS: C | CatalogoServicio@show | — | no |
| POST/PUT | `/catalogo-servicios[/{id}]` | SERVICIOS: O | CatalogoServicio@store/update | — | sí |
| DELETE | `/catalogo-servicios/{id}` | A | CatalogoServicio@destroy | 422 si está asignado | sí |
| GET | `/servicios-areas` | SERVICIOS u ORGANISMOS: C | ServicioArea@index | `area_ti_id`, `catalogo_servicio_id`, `estado_servicio`, `ministerio_id`, `per_page` (50) | sí |
| GET | `/servicios-areas/{id}` | SERVICIOS u ORGANISMOS: C | ServicioArea@show | — | no |
| POST/PUT | `/servicios-areas[/{id}]` | SERVICIOS: O | ServicioArea@store/update | `store` acepta `referentes[]` | sí |
| POST | `/servicios-areas/{id}/referentes` | SERVICIOS: O | ServicioArea@syncReferente | upsert por (servicio, persona, tipo) | sí |
| DELETE | `/servicios-areas/{id}/referentes/{referenteId}` | SERVICIOS: O | ServicioArea@removeReferente | borrado físico del referente | sí |
| DELETE | `/servicios-areas/{id}` | A | ServicioArea@destroy | — | sí |

### Firma digital (tokens)

| Método | Ruta | Perfil | Controlador@método | Filtros / notas | UI |
| :--- | :--- | :--- | :--- | :--- | :--- |
| GET | `/firma-digital/parametros` | FIRMA_DIGITAL: C | CertificadoFirma@parametros | vigencia (meses), anticipación (días), hora y máx. intentos vigentes | sí |
| GET | `/firma-digital/entregas` | FIRMA_DIGITAL: C | EntregaToken@index | `q` (persona, CUIL, documento, serie), `persona_id`, `ministerio_id`, `sin_organismo`, `organismo_externo`, `estado_entrega`, `estado_certificado` ∈ VIGENTE/PROXIMO_A_VENCER/VENCIDO/NO_VIGENTE (sobre el certificado actual), `vencimiento_desde/hasta`, `recordatorio_estado`, `per_page` (25, **tope 100**); `stats` | sí |
| GET | `/firma-digital/entregas/{id}` | FIRMA_DIGITAL: C | EntregaToken@show | con historial completo de certificados (más reciente primero) | sí |
| POST | `/firma-digital/entregas` | FIRMA_DIGITAL: O | EntregaToken@store | `persona_id`, `ministerio_id` **o** `organismo_externo` (excluyentes, ambos opcionales), `cargo_funcion`, `fecha_entrega`, `dispositivo_firma_id` (existente DISPONIBLE/DEVUELTO) **o** `dispositivo{tipo_dispositivo, marca, modelo, numero_serie}`, `certificado{fecha_inicio?, vencimiento_manual, fecha_vencimiento?, recordatorio_manual, fecha_recordatorio?}`, `reemplaza_entrega_id` (cierra como REEMPLAZADA una entrega asignada de la misma persona). Transaccional | sí |
| PUT | `/firma-digital/entregas/{id}` | FIRMA_DIGITAL: O | EntregaToken@update | organismo, cargo, fecha de entrega, observaciones | sí |
| POST | `/firma-digital/entregas/{id}/finalizar` | FIRMA_DIGITAL: O | EntregaToken@finalizar | `estado` ∈ DEVUELTA/EXTRAVIADA/REVOCADA, `fecha_fin`, `motivo_fin`, `estado_dispositivo`; cancela recordatorios pendientes | sí |
| POST | `/firma-digital/entregas/{id}/certificados` | FIRMA_DIGITAL: O | CertificadoFirma@store | renovación en el mismo token; cancela el recordatorio del certificado anterior | sí |
| PUT | `/firma-digital/certificados/{id}` | FIRMA_DIGITAL: O | CertificadoFirma@update | corrección de fechas (mismos campos `*_manual`) | sí |
| POST | `/firma-digital/certificados/{id}/reprogramar-recordatorio` | FIRMA_DIGITAL: O | CertificadoFirma@reprogramarRecordatorio | vuelve a PENDIENTE (intentos en 0); `fecha_recordatorio` opcional | sí |
| GET | `/firma-digital/certificados` | FIRMA_DIGITAL: C | CertificadoFirma@index | seguimiento de recordatorios: `recordatorio_estado`, `estado_certificado`, `recordatorio_desde/hasta`, `ministerio_id`, `q`, `incluir_historicos` (por defecto solo el certificado actual de cada entrega) | sí |
| GET | `/firma-digital/dispositivos[/{id}]` | FIRMA_DIGITAL: C | DispositivoFirma@index/show | `estado`, `solo_disponibles`, `q` | sí (stock en el alta) |
| POST/PUT | `/firma-digital/dispositivos[/{id}]` | FIRMA_DIGITAL: O | DispositivoFirma@store/update | el estado ASIGNADO solo lo manejan las entregas | no |
| DELETE | `/firma-digital/entregas/{id}` | A | EntregaToken@destroy | soft delete (cargas erróneas); libera el dispositivo | sí |
| DELETE | `/firma-digital/dispositivos/{id}` | A | DispositivoFirma@destroy | 422 si tiene entregas | no |

Fechas: si `*_manual` es `false` o falta, el backend calcula vencimiento = inicio + `FIRMA_VIGENCIA_MESES` y
recordatorio = vencimiento − `FIRMA_RECORDATORIO_DIAS` (`FirmaDigitalService`). Errores 422: vencimiento ≤ inicio
o recordatorio > vencimiento.

### Seguimiento de expedientes (permiso por módulo)

Perfil: **C** = rol CONSULTA o superior en el módulo · **O** = OPERADOR o superior · **S** = SUPERVISOR ·
**A** = perfil global ADMINISTRADOR (que además tiene SUPERVISOR en todos los módulos).

| Método | Ruta | Perfil | Notas |
| :--- | :--- | :--- | :--- |
| GET | `/expedientes` | C | Listado de **intervenciones**. Filtros: `situacion` ∈ EN_AREA (por defecto) / EGRESADAS / TODAS, `q` (número tolerante a espacios y guiones, carátula, motivo, GEDO), `estado_id`, `responsable_id` (o `sin`), `mis`, `ministerio_id`, `tipo_requerimiento_id`, `revisor_id`, `prioridad`, `dependencia_destino_id`, `ingreso_/egreso_/vencimiento_desde/hasta`, `accion_vencida`, `plazo_vencido`, `dias_min`, `orden` ∈ antiguedad / ingreso_desc / egreso_desc; `per_page` con tope de 100 |
| GET | `/expedientes/tablero` | C | Totales, cantidad en el área por estado, prioridades (15) y últimos movimientos (15) |
| GET | `/expedientes/opciones` | C | Catálogos e integrantes del módulo (usuarios con rol OPERADOR o superior) |
| GET | `/expedientes/buscar-numero?numero=` | C | ¿Existe? ¿Está en el área? Próximo número de intervención |
| GET | `/expedientes/exportar` | C | CSV con los mismos filtros; **requiere token** (la UI descarga con `fetch`) |
| GET | `/expedientes/{id}` | C | Ficha: expediente, intervenciones (más reciente primero) y movimientos |
| POST | `/expedientes` | O | Ingreso: crea el expediente o registra un reingreso (motivo obligatorio). 422 si ya está en el área |
| PUT | `/expedientes/intervenciones/{id}` | O | Datos editables (carátula, organismo, requerimiento, prioridad, próxima acción, vencimiento, ETB, acto, documento, observaciones) → movimiento ACTUALIZACION |
| POST | `/expedientes/intervenciones/{id}/asignar` | O | Tomar uno sin responsable: O; reasignar a otra persona: S. Responsables y colaboradores deben tener rol O o superior |
| POST | `/expedientes/intervenciones/{id}/estado` | O | Solo estados de trabajo (no finales) |
| POST | `/expedientes/intervenciones/{id}/revision` | O | Revisor, fecha, estado opcional, documento |
| POST | `/expedientes/intervenciones/{id}/firma` | O | Fecha y documento |
| POST | `/expedientes/intervenciones/{id}/egreso` | O | Fecha, estado final, `dependencia_destino_id` o `dependencia_destino_nombre` (se crea si no existe), documento obligatorio salvo estados que lo eximen |
| POST | `/expedientes/intervenciones/{id}/notas` | O | Nota; con `fecha_proximo_paso` actualiza la próxima acción |
| POST | `/expedientes/intervenciones/{id}/reabrir` | S | Solo la última intervención, egresada, con motivo |
| POST | `/expedientes/intervenciones/{id}/corregir-fechas` | S | Ingreso, egreso, revisión, firma, con motivo |
| POST/PUT | `/expedientes/catalogos/{estados\|requerimientos\|destinos}[/{id}]` | A | Sin borrado: se desactivan |
| DELETE | `/expedientes/{id}` | A | Baja lógica con `motivo`; oculta sus intervenciones y conserva los movimientos |

Fechas futuras y anteriores al ingreso se rechazan con 422. Cada acción inserta un movimiento (solo inserción).

### Usuarios

| Método | Ruta | Perfil | Notas |
| :--- | :--- | :--- | :--- |
| GET | `/usuarios` | A | `q`, `activo`, `perfil` ∈ ADMINISTRADOR/USUARIO, `modulo` (incluye a los administradores); cada usuario trae `ultimo_cambio_permisos` {fecha, autor}; `meta` con perfiles, roles y `catalogo_modulos` |
| POST/PUT | `/usuarios[/{id}]` | A | `name`, `email`, `perfil_acceso`, `activo`, `password` (≥ 8; opcional al editar), `perfil_acceso` ∈ ADMINISTRADOR/USUARIO, `modulos[{modulo, rol}]` (se ignoran para un administrador). No se borran; desactivar revoca sus tokens; un administrador no puede desactivarse ni quitarse el perfil, y siempre debe quedar al menos un administrador activo (422). Los cambios de perfil, estado o módulos se auditan con la acción PERMISOS; los de datos, con UPDATE (D-34) |

`POST /auth/login` y `GET /auth/user` devuelven `user.modulos` (rol efectivo por módulo).

Además, fuera de `/api`: `GET /` (JSON de estado, `routes/web.php`) y `GET /up` (health check de Laravel).

**No existen** endpoints para gestionar usuarios, consultar la auditoría ni cambiar contraseñas, aunque
el README atribuye esas capacidades al Administrador (ver `docs/product.md`).

Al cambiar la API: actualizar esta tabla, `useApi`/páginas consumidoras y, si cambia un mensaje de
validación en inglés, `frontend/composables/useToast.ts`.
