# Contrato de la API

Base: `http://localhost:8000/api`. Fuente de verdad: `backend/routes/api.php`. Para verlo en vivo:
`wslc exec erp_backend php artisan route:list --path=api` (86 rutas al 2026-10-23: 16 de firma digital, 19 de expedientes y 3 de usuarios).

Convenciones de respuesta:

- Éxito: `{ "success": true, "message"?: string, "data": ..., "stats"?: {...} }`.
- Validación: 422 estándar de Laravel `{ message, errors: { campo: [..] } }`, con mensajes en español
  (`backend/lang/es/validation.php`).
- Regla de negocio violada: 422 `{ success: false, message }`.
- Sin token: 401; perfil insuficiente: 403 `{ success:false, message, perfil_actual, perfiles_requeridos }`.
- Listados paginados (`personas`, `asignaciones-rol`, `servicios-areas`) devuelven el paginador de Laravel
  dentro de `data` (`data.data`, `data.total`, …) y aceptan `per_page` **sin tope máximo**.

Leyenda de perfil: **Pública** = sin `auth:sanctum` · **Auth** = cualquier perfil · **E** = ADMINISTRADOR o EDITOR ·
**A** = solo ADMINISTRADOR. «UI» indica si alguna página del frontend usa el endpoint.

| Método | Ruta | Perfil | Controlador@método | Filtros / notas | UI |
| :--- | :--- | :--- | :--- | :--- | :--- |
| POST | `/auth/login` | Pública | Auth@login | email, password → token | sí |
| GET | `/auth/user` | Auth | Auth@user | usuario + persona | no |
| POST | `/auth/logout` | Auth | Auth@logout | revoca token actual | sí |
| GET | `/dashboard/stats` | Auth | Dashboard@stats | totales, servicios por tipo, últimas 5 asignaciones | sí |
| POST | `/export/preview` | Auth | Export@preview | `ministerio_ids[]`, `area_ti_ids[]`, `tipo_rol_ids[]`, `solo_vigentes`, `solo_maximas_autoridades`, `solo_homologadas`, `incluir_alternativos` (sin validación; solo `$request->only`). Fuente: solo `asignaciones_rol`, **no** incluye a los referentes de servicios. No se audita | sí |
| GET | `/export/download` | **Pública** | Export@downloadCsv | mismos filtros que preview **salvo** `incluir_alternativos`, por query (`tipo_rol_ids=1,2`, booleanos como `1`; sin validación ni `filter_var`). `solo_vigentes` y `solo_homologadas` valen `true` si se omiten (ver DEF-15); CSV `;` con BOM UTF-8 | sí (`window.open`) |
| GET | `/documentos/{id}/descargar` | **Pública** | DocumentoAdjunto@download | archivo binario | sí (`<a href>`) |
| POST | `/documentos/upload` | E | DocumentoAdjunto@upload | multipart: `documentable_type` ∈ area_ti/servicio_area/asignacion_rol/persona, `documentable_id`, `tipo_documento`, `titulo`, `archivo` (≤20 MB), `observaciones` | sí (solo área TI) |
| DELETE | `/documentos/{id}` | A | DocumentoAdjunto@destroy | soft delete; el archivo físico queda | no |
| GET | `/ministerios` | Auth | Ministerio@index | `q`, `activo`; incluye `autoridad_vigente.persona` | sí |
| GET | `/ministerios/{id}` | Auth | Ministerio@show | con áreas (y su autoridad vigente), `autoridad_vigente` y el historial `autoridades` del organismo | sí |
| POST/PUT | `/ministerios[/{id}]` | E | Ministerio@store/update | `codigo`, `nombre`, `sigla`, `orden_prioridad`, `activo` | sí |
| DELETE | `/ministerios/{id}` | A | Ministerio@destroy | 422 si tiene áreas | sí |
| POST | `/ministerios/{id}/autoridad` | E | AutoridadOrganismo@designar | `persona_id`, `cargo?`, `fecha_inicio?`, `acto_administrativo?`, `observaciones?`; cierra la vigente el día anterior; 422 si es la misma persona (D-32) | sí |
| PUT | `/autoridades-organismo/{id}` | E | AutoridadOrganismo@update | corrige `cargo`, fechas, acto y observaciones (no la persona) | sí |
| DELETE | `/autoridades-organismo/{id}` | A | AutoridadOrganismo@destroy | baja lógica de una designación errónea | sí |
| GET | `/areas-ti` | Auth | AreaTi@index | `ministerio_id`, `es_homologada`, `nivel_jerarquico`, `q`; devuelve `stats` | sí |
| GET | `/areas-ti/{id}` | Auth | AreaTi@show | expediente: asignaciones, servicios, referentes, documentos | sí |
| POST/PUT | `/areas-ti[/{id}]` | E | AreaTi@store/update | — | sí |
| POST | `/areas-ti/{id}/maxima-autoridad` | E | AreaTi@designarAutoridad | `AutoridadAreaService` (D-33): cierra las autoridades abiertas o con cese futuro con cese el día anterior a `fecha_inicio`; 422 en `persona_id` si ya es la autoridad vigente y en `fecha_inicio` si no es posterior al inicio de la vigente | sí |
| DELETE | `/areas-ti/{id}` | A | AreaTi@destroy | 422 si tiene servicios | sí |
| GET | `/personas` | Auth | Persona@index | `q` (apellido/nombre/CUIL/emails/título), `ministerio_id`, `area_ti_id`, `tipo_rol_id`, `solo_autoridades`, `estado_cargo` ∈ VIGENTE/SIN_CARGO, `per_page` (20); `stats` | sí |
| GET | `/personas/{id}` | Auth | Persona@show | historial completo de cargos y referencias | sí |
| POST/PUT | `/personas[/{id}]` | E | Persona@store/update | obligatorios solo `apellido` y `nombre` (D-19); `cuil_cuit`, `email_principal`, `telefono_principal` opcionales; `tipo_documento` ∈ DNI/PASAPORTE/CI/LC/LE/OTRO y `numero_documento` van juntos (único por tipo); `q` del listado también busca por documento; `show` incluye `entregas_token` | sí |
| DELETE | `/personas/{id}` | A | Persona@destroy | 422 si tiene asignaciones vigentes | sí |
| GET | `/tipos-rol` | Auth | TipoRol@index | `activo`, `nivel_responsabilidad`; cuenta asignaciones vigentes | sí |
| GET | `/tipos-rol/{id}` | Auth | TipoRol@show | — | no |
| POST/PUT/DELETE | `/tipos-rol[/{id}]` | A | TipoRol@store/update/destroy | borrar: 422 si tiene asignaciones | **no** |
| GET | `/asignaciones-rol` | Auth | AsignacionRol@index | `tipo_rol_id`, `ministerio_id`, `area_ti_id`, `persona_id`, `estado` ∈ VIGENTE/HISTORICO, `solo_maximas_autoridades`, `q`, `per_page` (25) | sí |
| GET | `/asignaciones-rol/{id}` | Auth | AsignacionRol@show | — | no |
| POST/PUT | `/asignaciones-rol[/{id}]` | E | AsignacionRol@store/update | `es_maxima_autoridad` por defecto según el tipo de rol; si es máxima autoridad y no tiene `fecha_fin`, aplica las reglas de D-33 (mismo cierre y mismos 422 que `/areas-ti/{id}/maxima-autoridad`) | sí |
| POST | `/asignaciones-rol/{id}/finalizar-vigencia` | E | AsignacionRol@finalizarVigencia | `fecha_fin`, `motivo` (se agrega a observaciones) | sí |
| DELETE | `/asignaciones-rol/{id}` | A | AsignacionRol@destroy | para cargas erróneas | sí |
| GET | `/catalogo-servicios` | Auth | CatalogoServicio@index | `activo`, `categoria` | sí |
| GET | `/catalogo-servicios/{id}` | Auth | CatalogoServicio@show | — | no |
| POST/PUT | `/catalogo-servicios[/{id}]` | E | CatalogoServicio@store/update | — | sí |
| DELETE | `/catalogo-servicios/{id}` | A | CatalogoServicio@destroy | 422 si está asignado | sí |
| GET | `/servicios-areas` | Auth | ServicioArea@index | `area_ti_id`, `catalogo_servicio_id`, `estado_servicio`, `ministerio_id`, `per_page` (50) | sí |
| GET | `/servicios-areas/{id}` | Auth | ServicioArea@show | — | no |
| POST/PUT | `/servicios-areas[/{id}]` | E | ServicioArea@store/update | `store` acepta `referentes[]` | sí |
| POST | `/servicios-areas/{id}/referentes` | E | ServicioArea@syncReferente | upsert por (servicio, persona, tipo) | sí |
| DELETE | `/servicios-areas/{id}/referentes/{referenteId}` | E | ServicioArea@removeReferente | borrado físico del referente | sí |
| DELETE | `/servicios-areas/{id}` | A | ServicioArea@destroy | — | sí |

### Firma digital (tokens)

| Método | Ruta | Perfil | Controlador@método | Filtros / notas | UI |
| :--- | :--- | :--- | :--- | :--- | :--- |
| GET | `/firma-digital/parametros` | Auth | CertificadoFirma@parametros | vigencia (meses), anticipación (días), hora y máx. intentos vigentes | sí |
| GET | `/firma-digital/entregas` | Auth | EntregaToken@index | `q` (persona, CUIL, documento, serie), `persona_id`, `ministerio_id`, `sin_organismo`, `organismo_externo`, `estado_entrega`, `estado_certificado` ∈ VIGENTE/PROXIMO_A_VENCER/VENCIDO/NO_VIGENTE (sobre el certificado actual), `vencimiento_desde/hasta`, `recordatorio_estado`, `per_page` (25, **tope 100**); `stats` | sí |
| GET | `/firma-digital/entregas/{id}` | Auth | EntregaToken@show | con historial completo de certificados (más reciente primero) | sí |
| POST | `/firma-digital/entregas` | E | EntregaToken@store | `persona_id`, `ministerio_id` **o** `organismo_externo` (excluyentes, ambos opcionales), `cargo_funcion`, `fecha_entrega`, `dispositivo_firma_id` (existente DISPONIBLE/DEVUELTO) **o** `dispositivo{tipo_dispositivo, marca, modelo, numero_serie}`, `certificado{fecha_inicio?, vencimiento_manual, fecha_vencimiento?, recordatorio_manual, fecha_recordatorio?}`, `reemplaza_entrega_id` (cierra como REEMPLAZADA una entrega asignada de la misma persona). Transaccional | sí |
| PUT | `/firma-digital/entregas/{id}` | E | EntregaToken@update | organismo, cargo, fecha de entrega, observaciones | sí |
| POST | `/firma-digital/entregas/{id}/finalizar` | E | EntregaToken@finalizar | `estado` ∈ DEVUELTA/EXTRAVIADA/REVOCADA, `fecha_fin`, `motivo_fin`, `estado_dispositivo`; cancela recordatorios pendientes | sí |
| POST | `/firma-digital/entregas/{id}/certificados` | E | CertificadoFirma@store | renovación en el mismo token; cancela el recordatorio del certificado anterior | sí |
| PUT | `/firma-digital/certificados/{id}` | E | CertificadoFirma@update | corrección de fechas (mismos campos `*_manual`) | sí |
| POST | `/firma-digital/certificados/{id}/reprogramar-recordatorio` | E | CertificadoFirma@reprogramarRecordatorio | vuelve a PENDIENTE (intentos en 0); `fecha_recordatorio` opcional | sí |
| GET | `/firma-digital/certificados` | Auth | CertificadoFirma@index | seguimiento de recordatorios: `recordatorio_estado`, `estado_certificado`, `recordatorio_desde/hasta`, `ministerio_id`, `q`, `incluir_historicos` (por defecto solo el certificado actual de cada entrega) | sí |
| GET | `/firma-digital/dispositivos[/{id}]` | Auth | DispositivoFirma@index/show | `estado`, `solo_disponibles`, `q` | sí (stock en el alta) |
| POST/PUT | `/firma-digital/dispositivos[/{id}]` | E | DispositivoFirma@store/update | el estado ASIGNADO solo lo manejan las entregas | no |
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
| GET | `/usuarios` | A | `q`, `activo`; `meta` con perfiles y roles por módulo |
| POST/PUT | `/usuarios[/{id}]` | A | `name`, `email`, `perfil_acceso`, `activo`, `password` (≥ 8; opcional al editar), `modulos[{modulo, rol}]`. No se borran; desactivar revoca sus tokens; un administrador no puede desactivarse ni quitarse el perfil |

`POST /auth/login` y `GET /auth/user` devuelven `user.modulos` (rol efectivo por módulo).

Además, fuera de `/api`: `GET /` (JSON de estado, `routes/web.php`) y `GET /up` (health check de Laravel).

**No existen** endpoints para gestionar usuarios, consultar la auditoría ni cambiar contraseñas, aunque
el README atribuye esas capacidades al Administrador (ver `docs/product.md`).

Al cambiar la API: actualizar esta tabla, `useApi`/páginas consumidoras y, si cambia un mensaje de
validación en inglés, `frontend/composables/useToast.ts`.
